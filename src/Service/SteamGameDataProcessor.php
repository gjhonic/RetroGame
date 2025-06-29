<?php

namespace App\Service;

use App\Entity\Game;
use App\Entity\GameShop;
use App\Entity\Genre;
use App\Entity\Shop;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SteamGameDataProcessor
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Обрабатывает данные из Steam API и создает Game и GameShop
     *  Возвращает true если игра была успешно обработана, false если пропущена
     *
     * @param array<mixed> $detailsData
     * @param OutputInterface|null $output
     * @param array<mixed> $existingGameNames
     * @param array<mixed> $existingGameShopIds
     * @return bool
     */
    public function processGameData(
        array $detailsData,
        ?OutputInterface $output = null,
        array &$existingGameNames = [],
        array $existingGameShopIds = []
    ): bool {
        // Извлекаем appId из данных
        $appId = $this->extractAppId($detailsData);
        if (!$appId) {
            if ($output) {
                $output->writeln("<comment>⚠️ Не удалось извлечь appId из данных</comment>");
            }
            return false;
        }

        $raw = $detailsData[$appId] ?? null;
        $success = $raw['success'] ?? false;
        $gameData = $raw['data'] ?? null;

        if (!$success || empty($gameData)) {
            if ($output) {
                $output->writeln(
                    "<comment>" .
                    "⚠️ Приложение {$appId} пустое или не удалось загрузить. Сохраняем как type=empty.</comment>"
                );
            }
            return false;
        }

        if ($output) {
            $output->writeln("✅ <info>Детали приложения {$appId} загружены и сохранены.</info>");
        }

        // Проверяем, является ли приложение игрой
        if (!$this->isGame($gameData)) {
            if ($output) {
                $output->writeln("<comment>⏩ Приложение {$appId} не является игрой.</comment>");
            }
            return false;
        }

        // Проверяем, существует ли GameShop
        if (!empty($existingGameShopIds) && isset($existingGameShopIds[$appId])) {
            if ($output) {
                $output->writeln("<comment>⏩ Приложение {$appId} уже связано с GameShop.</comment>");
            }
            return false;
        }

        // Получаем магазин Steam
        $shop = $this->getSteamShop();
        if (!$shop) {
            if ($output) {
                $output->writeln("<error>⛔ Магазин Steam (id=1) не найден в базе данных.</error>");
            }
            return false;
        }

        // Извлекаем имя игры
        $gameName = $this->extractGameName($gameData);
        if (!$gameName) {
            if ($output) {
                $output->writeln("<comment>⚠️ Не удалось извлечь имя игры для appId {$appId}</comment>");
            }
            return false;
        }

        // Создаём или ищем Game
        $game = null;

        // Проверяем в предзагруженном списке
        if (!empty($existingGameNames) && isset($existingGameNames[$gameName])) {
            $game = $this->entityManager
                ->getRepository(Game::class)
                ->findOneBy(['name' => $gameName]);
        }

        // Если не найдено в кэше или кэш пустой, ищем в базе
        if (!$game) {
            $game = $this->entityManager
                ->getRepository(Game::class)
                ->findOneBy(['name' => $gameName]);
        }

        if (!$game) {
            $game = $this->createNewGame($gameData, $gameName, $output, $existingGameNames);
        }

        // Обрабатываем жанры
        $this->processGenres($game, $gameData, $output);

        // Создаем GameShop
        $this->createGameShop($game, $shop, $appId, $gameName, $output);

        return true;
    }

    /**
     * Извлекает appId из данных Steam API
     *
     * @param array<mixed> $detailsData
     * @return int|null
     */
    private function extractAppId(array $detailsData): ?int
    {
        $keys = array_keys($detailsData);
        return !empty($keys) ? (int) $keys[0] : null;
    }

    /**
     * Извлекает имя игры из данных
     *
     * @param array<mixed> $gameData
     * @return string|null
     */
    private function extractGameName(array $gameData): ?string
    {
        $name = $gameData['name'] ?? null;
        return $name ? trim($name) : null;
    }

    /**
     * Получает магазин Steam из базы данных
     */
    private function getSteamShop(): ?Shop
    {
        return $this->entityManager
            ->getRepository(Shop::class)
            ->find(1);
    }

    /**
     * Создает новую игру на основе данных из Steam API
     *
     * @param array<mixed> $gameData
     * @param string $gameName
     * @param OutputInterface|null $output
     * @param array<mixed> $existingGameNames
     * @return Game
     */
    private function createNewGame(
        array $gameData,
        string $gameName,
        ?OutputInterface $output,
        array &$existingGameNames = []
    ): Game {
        $recommendations = $gameData['recommendations']['total'] ?? null;
        $ownersCount = null;

        if ($recommendations !== null) {
            $ownersCount = (int) $recommendations;
        }

        $game = new Game();
        $game->setName($gameName);
        $game->setDescription($gameData['short_description']);
        $game->setIsFree(!empty($gameData['is_free']));
        $game->setOwnersCount($ownersCount);

        // Сохраняем изображение
        $this->saveGameImage($game, $gameData, $output);

        // Устанавливаем дату релиза
        $this->setReleaseDate($game, $gameData);

        $this->entityManager->persist($game);

        // Добавляем в кэш для последующих итераций
        $existingGameNames[$gameName] = true;

        if ($output) {
            $output->writeln("🆕 <info>Создана новая игра: '{$gameName}'</info>");
        }

        return $game;
    }

    /**
     * Создает GameShop для игры
     *
     * @param Game $game
     * @param Shop $shop
     * @param int $appId
     * @param string $gameName
     * @param OutputInterface|null $output
     * @return void
     */
    private function createGameShop(
        Game $game,
        Shop $shop,
        int $appId,
        string $gameName,
        ?OutputInterface $output
    ): void {
        $gameShop = new GameShop();
        $gameShop->setGame($game);
        $gameShop->setShop($shop);
        $gameShop->setLinkGameId($appId);
        $gameShop->setName($gameName);
        $gameShop->setLink("https://store.steampowered.com/app/{$appId}/");
        $gameShop->setShouldImportPrice(true);

        $this->entityManager->persist($gameShop);

        if ($output) {
            $output->writeln("🛒 <info>Создан GameShop для игры '{$gameName}' в магазине {$shop->getName()}</info>");
        }
    }

    /**
     * Сохраняет изображение игры
     *
     * @param Game $game
     * @param array<mixed> $gameData
     * @param OutputInterface|null $output
     * @return void
     */
    private function saveGameImage(Game $game, array $gameData, ?OutputInterface $output): void
    {
        $imageUrl = $gameData['header_image'] ?? null;
        if (!$imageUrl) {
            return;
        }

        try {
            $imageContents = file_get_contents($imageUrl);
            if ($imageContents === false) {
                if ($output) {
                    $output->writeln("<comment>⚠️ Не удалось загрузить изображение</comment>");
                }
                return;
            }

            $imageName = uniqid('game_') . '.jpg';
            $savePath = __DIR__ . '/../../public/uploads/games/' . $imageName;

            if (!is_dir(dirname($savePath))) {
                mkdir(dirname($savePath), 0777, true);
            }

            if (file_put_contents($savePath, $imageContents) !== false) {
                $game->setImage('/uploads/games/' . $imageName);
                if ($output) {
                    $output->writeln("🖼️ <info>Изображение сохранено: {$imageName}</info>");
                }
            }
        } catch (\Throwable $e) {
            if ($output) {
                $output->writeln("<comment>⚠️ Ошибка при сохранении изображения: {$e->getMessage()}</comment>");
            }
        }
    }

    /**
     * Устанавливает дату релиза игры
     *
     * @param Game $game
     * @param array<mixed> $gameData
     * @return void
     */
    private function setReleaseDate(Game $game, array $gameData): void
    {
        try {
            $releaseDate = $gameData['release_date']['date'] ?? '2000-01-01';
            $game->setReleaseDate(new \DateTime($releaseDate));
        } catch (\Exception) {
            $game->setReleaseDate(new \DateTime('2000-01-01'));
        }
    }

    /**
     * Обрабатывает жанры игры
     *
     * @param Game $game
     * @param array<mixed> $gameData
     * @param OutputInterface|null $output
     * @return void
     */
    private function processGenres(Game $game, array $gameData, ?OutputInterface $output): void
    {
        $genres = $gameData['genres'] ?? [];
        if (empty($genres)) {
            return;
        }

        $genreCache = [];

        foreach ($genres as $genreItem) {
            $genreName = trim($genreItem['description']);
            if (empty($genreName)) {
                continue;
            }

            // Используем кэш для жанров
            if (isset($genreCache[$genreName])) {
                $genre = $genreCache[$genreName];
            } else {
                $genre = $this->entityManager
                    ->getRepository(Genre::class)
                    ->findOneBy(['name' => $genreName]);

                if (!$genre) {
                    $genre = new Genre();
                    $genre->setName($genreName);
                    $genre->setCreatedAt(new \DateTimeImmutable());
                    $genre->setCreatedBy('system');
                    $this->entityManager->persist($genre);

                    if ($output) {
                        $output->writeln("🏷️ <info>Создан новый жанр: '{$genreName}'</info>");
                    }
                }

                $genreCache[$genreName] = $genre;
            }

            if (!$game->getGenre()->contains($genre)) {
                $game->addGenre($genre);
            }
        }
    }

    /**
     * Проверяет, является ли приложение игрой
     *
     * @param array<mixed> $gameData
     * @return bool
     */
    public function isGame(array $gameData): bool
    {
        return ($gameData['type'] ?? '') === 'game' &&
               !empty($gameData['short_description']) &&
               !empty($gameData['genres']) &&
               !empty($gameData['price_overview']);
    }

    /**
     * Получает количество владельцев из данных Steam
     *
     * @param array<mixed> $gameData
     * @return int|null
     */
    public function getOwnersCount(array $gameData): ?int
    {
        $recommendations = $gameData['recommendations']['total'] ?? null;

        if ($recommendations !== null) {
            return (int) $recommendations;
        }

        return null;
    }
}
