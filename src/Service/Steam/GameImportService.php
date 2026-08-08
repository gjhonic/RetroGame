<?php

namespace App\Service\Steam;

use App\Entity\Developer;
use App\Entity\Game;
use App\Entity\Genre;
use App\Entity\Platform;
use App\Entity\Publisher;
use App\Entity\SteamGame;
use App\Repository\GameRepository;
use App\Repository\SteamGameRepository;
use App\Repository\SteamImportCursorRepository;
use App\Service\Image\GameImageDownloader;
use App\Service\Steam\Exceptions\SteamApiException;
use App\Service\Steam\Interfaces\RateLimiterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Вся логика импорта игр из Steam: постраничная загрузка каталога,
 * дозагрузка деталей по каждой игре, сохранение в Game/SteamGame
 * и фиксация неудачных попыток в SteamGame::status.
 */
class GameImportService
{
    /**
     * Кэш уже найденных/созданных справочных сущностей (Developer, Genre и
     * т.д.) в рамках текущего запуска — чтобы не плодить дубли по имени
     * и не долбить БД повторными запросами за один и тот же импорт.
     *
     * @var array<string, object>
     */
    private array $namedEntityCache = [];

    /** Принимает все зависимости, нужные для импорта и сохранения игр. */
    public function __construct(
        private readonly SteamClient $steamClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly GameRepository $gameRepository,
        private readonly SteamGameRepository $steamGameRepository,
        private readonly SteamImportCursorRepository $cursorRepository,
        private readonly SluggerInterface $slugger,
        private readonly RateLimiterInterface $rateLimiter,
        private readonly GameImageDownloader $imageDownloader,
        private readonly SteamReleaseDateParser $releaseDateParser,
    ) {
    }

    /**
     * Импортирует следующую порцию игр из каталога Steam.
     *
     * Если $lastAppId не задан — продолжает с сохранённого курсора (для
     * cron, чтобы не начинать каждый раз с начала каталога); в любом
     * случае после порции курсор сдвигается на достигнутую позицию.
     */
    public function importNextBatch(int $limit, ?int $lastAppId, int $delayMs): ImportResult
    {
        $cursor = $this->cursorRepository->getOrCreate();
        $startAppId = $lastAppId ?? $cursor->getLastAppId();

        $page = $this->steamClient->fetchGameAppList($limit, $startAppId);
        $apps = $page['apps'];

        if ($apps === []) {
            return new ImportResult(steamGames: [], hasMore: false, lastAppId: $startAppId);
        }

        $steamGames = [];

        foreach ($apps as $i => $app) {
            $steamGame = $this->findOrCreateSteamGame((int) $app['appid'], (string) $app['name']);
            $steamGames[] = $this->fetchAndStore($steamGame);

            if ($i !== array_key_last($apps)) {
                $this->rateLimiter->delay($delayMs);
            }
        }

        $cursor->setLastAppId($page['lastAppId']);
        $this->entityManager->flush();

        return new ImportResult(steamGames: $steamGames, hasMore: $page['hasMore'], lastAppId: $page['lastAppId']);
    }

    /** Находит существующую запись по appid или создаёт новую пару Game+SteamGame. */
    private function findOrCreateSteamGame(int $appId, string $name): SteamGame
    {
        $steamGame = $this->steamGameRepository->findOneBySteamAppId($appId);

        if ($steamGame !== null) {
            return $steamGame;
        }

        $game = new Game($name, $this->buildUniqueSlug($name, $appId));
        $steamGame = new SteamGame($game, $appId);

        $this->entityManager->persist($game);
        $this->entityManager->persist($steamGame);

        return $steamGame;
    }

    /** Загружает детали игры и фиксирует результат (успех или ошибку) в БД. */
    private function fetchAndStore(SteamGame $steamGame): SteamGame
    {
        $errorMessage = null;

        try {
            $details = $this->steamClient->fetchAppDetails($steamGame->getSteamAppId());
        } catch (SteamApiException $e) {
            $details = null;
            $errorMessage = $e->getMessage();
        }

        if ($details === null) {
            $steamGame->markFailure($errorMessage ?? 'Steam appdetails: данные недоступны (success=false)');
        } else {
            $steamGame->markSuccess($details);
            $this->applyDetailsToGame($steamGame->getGame(), $details, $steamGame->getSteamAppId());
        }

        $this->entityManager->flush();

        return $steamGame;
    }

    /**
     * Переносит поля из ответа Steam appdetails в базовую сущность Game
     * и скачивает обложку в public/uploads/games.
     *
     * @param array<string, mixed> $details
     */
    private function applyDetailsToGame(Game $game, array $details, int $steamAppId): void
    {
        if (isset($details['name'])) {
            $game->setName((string) $details['name']);
        }

        $game->setDescription($this->nullableString($details['short_description'] ?? null));
        $game->setReleaseDate($this->releaseDateParser->parse($details['release_date']['date'] ?? null));

        $coverImageUrl = $this->nullableString($details['header_image'] ?? null);
        $game->setCoverImagePath(
            $coverImageUrl === null ? null : $this->imageDownloader->downloadCover($coverImageUrl, $steamAppId),
        );

        $metacriticScore = $details['metacritic']['score'] ?? null;
        $game->setMetacriticScore($metacriticScore === null ? null : (int) $metacriticScore);

        $game->getDevelopers()->clear();
        foreach ($this->stringList($details['developers'] ?? null) as $name) {
            $game->addDeveloper($this->findOrCreateNamed(Developer::class, $name));
        }

        $game->getPublishers()->clear();
        foreach ($this->stringList($details['publishers'] ?? null) as $name) {
            $game->addPublisher($this->findOrCreateNamed(Publisher::class, $name));
        }

        $game->getGenres()->clear();
        foreach ($this->pluckDescriptions($details['genres'] ?? null) as $name) {
            $game->addGenre($this->findOrCreateNamed(Genre::class, $name));
        }

        $game->getPlatforms()->clear();
        foreach ($this->enabledPlatforms($details['platforms'] ?? null) as $name) {
            $game->addPlatform($this->findOrCreateNamed(Platform::class, $name));
        }

        $game->setScreenshotUrls($this->pluckUrls($details['screenshots'] ?? null));

        $game->touch();
    }

    /**
     * Находит справочную сущность (Developer/Publisher/Genre/Platform) по
     * названию или создаёт новую. Каждая из них имеет конструктор
     * __construct(string $name) и уникальную колонку name.
     *
     * @template T of object
     *
     * @param class-string<T> $entityClass
     *
     * @return T
     */
    private function findOrCreateNamed(string $entityClass, string $name): object
    {
        $cacheKey = $entityClass . '|' . $name;

        if (isset($this->namedEntityCache[$cacheKey])) {
            /** @var T */
            return $this->namedEntityCache[$cacheKey];
        }

        $entity = $this->entityManager->getRepository($entityClass)->findOneBy(['name' => $name]);

        if ($entity === null) {
            $entity = new $entityClass($name);
            $this->entityManager->persist($entity);
        }

        $this->namedEntityCache[$cacheKey] = $entity;

        return $entity;
    }

    /**
     * Приводит значение к списку строк (например, developers/publishers).
     *
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value) || $value === []) {
            return [];
        }

        return array_values(array_unique(array_map(static fn (mixed $item): string => (string) $item, $value)));
    }

    /**
     * Достаёт поле description из списка объектов вида {description: string}
     * (жанры Steam).
     *
     * @return array<int, string>
     */
    private function pluckDescriptions(mixed $value): array
    {
        if (!is_array($value) || $value === []) {
            return [];
        }

        return $this->stringList(array_column($value, 'description'));
    }

    /**
     * Достаёт поле path_full из списка скриншотов Steam.
     *
     * @return array<int, string>|null
     */
    private function pluckUrls(mixed $value): ?array
    {
        if (!is_array($value) || $value === []) {
            return null;
        }

        $urls = $this->stringList(array_column($value, 'path_full'));

        return $urls === [] ? null : $urls;
    }

    /**
     * Строит список включённых платформ ({windows,mac,linux: bool}).
     *
     * @return array<int, string>
     */
    private function enabledPlatforms(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $names = [
            'windows' => 'Windows',
            'mac' => 'macOS',
            'linux' => 'Linux',
        ];

        $platforms = [];
        foreach ($names as $key => $label) {
            if (!empty($value[$key])) {
                $platforms[] = $label;
            }
        }

        return $platforms;
    }

    /** Строит slug по названию, при коллизии добавляет appid. */
    private function buildUniqueSlug(string $name, int $steamAppId): string
    {
        $base = strtolower((string) $this->slugger->slug($name));

        if ($base === '' || $this->gameRepository->findOneBy(['slug' => $base]) !== null) {
            return $base . '-' . $steamAppId;
        }

        return $base;
    }

    /** Приводит значение к строке, пустую строку превращает в null. */
    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
