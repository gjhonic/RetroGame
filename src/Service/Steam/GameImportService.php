<?php

namespace App\Service\Steam;

use App\Entity\Developer;
use App\Entity\Dlc;
use App\Entity\Game;
use App\Entity\Genre;
use App\Entity\Interfaces\HasSteamDetailsInterface;
use App\Entity\Platform;
use App\Entity\Publisher;
use App\Entity\SteamGame;
use App\Repository\DlcRepository;
use App\Repository\GameRepository;
use App\Repository\SteamGameRepository;
use App\Repository\SteamImportCursorRepository;
use App\Service\Image\GameImageDownloader;
use App\Service\Steam\Exceptions\SteamApiException;
use App\Service\Steam\Interfaces\RateLimiterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Вся логика импорта данных из Steam: постраничная загрузка каталога,
 * дозагрузка деталей по каждому appid и сохранение по типу (appdetails.type):
 * 'game' → Game, 'dlc' → Dlc (со связью на базовую игру, если та уже
 * импортирована), остальные типы (demo/mod/software/...) — только сырые
 * данные в SteamGame::rawData, без Game/Dlc. Неудачные попытки загрузки
 * фиксируются в SteamGame::status.
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
        private readonly DlcRepository $dlcRepository,
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
            $steamGame = $this->findOrCreateSteamGame((int) $app['appid']);
            $steamGames[] = $this->fetchAndStore($steamGame, (string) $app['name']);

            if ($i !== array_key_last($apps)) {
                $this->rateLimiter->delay($delayMs);
            }
        }

        $cursor->setLastAppId($page['lastAppId']);
        $this->entityManager->flush();

        return new ImportResult(steamGames: $steamGames, hasMore: $page['hasMore'], lastAppId: $page['lastAppId']);
    }

    /** Находит существующую запись по appid или создаёт новую (без Game/Dlc — тип пока неизвестен). */
    private function findOrCreateSteamGame(int $appId): SteamGame
    {
        $steamGame = $this->steamGameRepository->findOneBySteamAppId($appId);

        if ($steamGame !== null) {
            return $steamGame;
        }

        $steamGame = new SteamGame($appId);
        $this->entityManager->persist($steamGame);

        return $steamGame;
    }

    /**
     * Загружает детали приложения и фиксирует результат в БД: при успехе —
     * в зависимости от appdetails.type сохраняет в Game, в Dlc или никуда
     * (demo/mod/software/...), при неудаче — помечает попытку неудачной.
     */
    private function fetchAndStore(SteamGame $steamGame, string $fallbackName): SteamGame
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

            match ((string) ($details['type'] ?? 'game')) {
                'game' => $this->applyGame($steamGame, $details, $fallbackName),
                'dlc' => $this->applyDlc($steamGame, $details, $fallbackName),
                default => null,
            };
        }

        $this->entityManager->flush();

        return $steamGame;
    }

    /**
     * Находит/создаёт Game для этой Steam-записи, переносит в неё общие поля
     * и Game-специфичные (metacritic, popularity), затем доотвязывает DLC,
     * ожидавшие импорта именно этой игры.
     *
     * @param array<string, mixed> $details
     */
    private function applyGame(SteamGame $steamGame, array $details, string $fallbackName): void
    {
        $game = $steamGame->getGame();

        if ($game === null) {
            $slug = $this->buildUniqueSlug($fallbackName, $steamGame->getSteamAppId(), $this->gameRepository);
            $game = new Game($fallbackName, $slug);
            $this->entityManager->persist($game);
            $steamGame->setGame($game);
        }

        $this->applyCommonDetails($game, $details, $steamGame->getSteamAppId());

        $metacriticScore = $details['metacritic']['score'] ?? null;
        $game->setMetacriticScore($metacriticScore === null ? null : (int) $metacriticScore);

        $popularity = $details['recommendations']['total'] ?? null;
        $game->setPopularity($popularity === null ? null : (int) $popularity);

        $this->relinkPendingDlcs($game, $steamGame->getSteamAppId());
    }

    /**
     * Находит/создаёт Dlc для этой Steam-записи, переносит в неё общие поля
     * и связывает с базовой игрой (details.fullgame.appid), если та уже
     * импортирована — иначе запоминает appid для доотвязки позже.
     *
     * @param array<string, mixed> $details
     */
    private function applyDlc(SteamGame $steamGame, array $details, string $fallbackName): void
    {
        $dlc = $steamGame->getDlc();

        if ($dlc === null) {
            $slug = $this->buildUniqueSlug($fallbackName, $steamGame->getSteamAppId(), $this->dlcRepository);
            $dlc = new Dlc($fallbackName, $slug);
            $this->entityManager->persist($dlc);
            $steamGame->setDlc($dlc);
        }

        $this->applyCommonDetails($dlc, $details, $steamGame->getSteamAppId());

        $baseGameAppId = $details['fullgame']['appid'] ?? null;
        $baseGameAppId = $baseGameAppId === null ? null : (int) $baseGameAppId;
        $baseGame = $baseGameAppId === null
            ? null
            : $this->steamGameRepository->findOneBySteamAppId($baseGameAppId)?->getGame();

        if ($baseGame !== null) {
            $dlc->setGame($baseGame)->setPendingBaseGameSteamAppId(null);
        } else {
            $dlc->setPendingBaseGameSteamAppId($baseGameAppId);
        }
    }

    /** Привязывает базовую игру к DLC, которые её ждали (pendingBaseGameSteamAppId). */
    private function relinkPendingDlcs(Game $game, int $steamAppId): void
    {
        foreach ($this->dlcRepository->findPendingBySteamAppId($steamAppId) as $dlc) {
            $dlc->setGame($game)->setPendingBaseGameSteamAppId(null);
        }
    }

    /**
     * Переносит общие поля из ответа Steam appdetails в Game/Dlc и скачивает
     * обложку в public/uploads/games.
     *
     * @param array<string, mixed> $details
     */
    private function applyCommonDetails(HasSteamDetailsInterface $entity, array $details, int $steamAppId): void
    {
        if (isset($details['name'])) {
            $entity->setName((string) $details['name']);
        }

        $entity->setDescription($this->nullableString($details['short_description'] ?? null));
        $entity->setReleaseDate($this->releaseDateParser->parse($details['release_date']['date'] ?? null));

        $coverImageUrl = $this->nullableString($details['header_image'] ?? null);
        $entity->setCoverImagePath(
            $coverImageUrl === null ? null : $this->imageDownloader->downloadCover($coverImageUrl, $steamAppId),
        );

        $entity->getDevelopers()->clear();
        foreach ($this->stringList($details['developers'] ?? null) as $name) {
            $entity->addDeveloper($this->findOrCreateNamed(Developer::class, $name));
        }

        $entity->getPublishers()->clear();
        foreach ($this->stringList($details['publishers'] ?? null) as $name) {
            $entity->addPublisher($this->findOrCreateNamed(Publisher::class, $name));
        }

        $entity->getGenres()->clear();
        foreach ($this->pluckDescriptions($details['genres'] ?? null) as $name) {
            $entity->addGenre($this->findOrCreateNamed(Genre::class, $name));
        }

        $entity->getPlatforms()->clear();
        foreach ($this->enabledPlatforms($details['platforms'] ?? null) as $name) {
            $entity->addPlatform($this->findOrCreateNamed(Platform::class, $name));
        }

        $entity->setScreenshotUrls($this->pluckUrls($details['screenshots'] ?? null));

        $entity->touch();
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

    /**
     * Строит slug по названию, при коллизии добавляет appid. $repository —
     * GameRepository или DlcRepository, у slug'а каждого свой неймспейс уникальности.
     *
     * Колонка slug — varchar(255), поэтому базу обрезаем с запасом под суффикс
     * "-{appid}": транслитерация (кириллица/иероглифы → латиница) может
     * растянуть строку длиннее исходного названия и превысить лимит колонки,
     * даже если само название укладывается в него.
     */
    private function buildUniqueSlug(string $name, int $steamAppId, GameRepository|DlcRepository $repository): string
    {
        $maxLength = 255;
        $suffix = '-' . $steamAppId;

        $base = strtolower((string) $this->slugger->slug($name));
        $base = mb_substr($base, 0, $maxLength - mb_strlen($suffix));

        if ($base === '' || $repository->findOneBy(['slug' => $base]) !== null) {
            return $base . $suffix;
        }

        return $base;
    }

    /** Приводит значение к строке, пустую строку превращает в null. */
    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
