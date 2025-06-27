<?php

namespace App\Command;

use App\Entity\Game;
use App\Entity\GameShop;
use App\Entity\Genre;
use App\Entity\Shop;
use App\Entity\SteamApp;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:steam-get-games',
    description: 'Fetches popular Steam games and stores them in the database.',
)]
class SteamGetGamesCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('🚀 <info>Начинаем загрузку списка приложений Steam...</info>');

        try {
            $response = $this->httpClient->request('GET', 'https://api.steampowered.com/ISteamApps/GetAppList/v2/');
            $data = $response->toArray();
        } catch (\Throwable $e) {
            $output->writeln('<error>⛔ Не удалось получить список приложений: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $apps = $data['applist']['apps'] ?? [];
        $output->writeln('🔍 <info>Всего приложений найдено: ' . count($apps) . '</info>');

        $shopSteam = $this->entityManager
            ->getRepository(Shop::class)
            ->find(1);

        if (!$shopSteam) {
            $output->writeln('<error>⛔ Магазин Steam (id=1) не найден в базе данных.</error>');
            return Command::FAILURE;
        }

        $imported = 0;
        $checked = 0;
        $genreCache = [];
        $batchSize = 10;

        foreach ($apps as $app) {
            if ($imported >= 200) {
                $output->writeln('⏹️ <comment>Достигнут лимит 200 новых игр. Останавливаем импорт.</comment>');
                break;
            }

            $appid = $app['appid'] ?? null;
            $gameName = trim($app['name'] ?? '');

            if (!$appid || !$gameName) {
                continue;
            }

            // Уже обработано?
            $existingSteamApp = $this->entityManager
                ->getRepository(SteamApp::class)
                ->findOneBy(['app_id' => $appid]);

            if ($existingSteamApp) {
                $output->writeln("<comment>⏩ Приложение {$appid} уже импортировано.</comment>");
                continue;
            }

            if ($checked === 30) {
                $this->entityManager->flush();
                $output->writeln("⏳ <info>Обработано 30 приложений, пауза 10 секунд...</info>");

                usleep(random_int(7000000, 10000000));
                $checked = 0;
            }

            $checked++;

            // Получаем подробную информацию
            try {
                $detailsResponse = $this->httpClient->request(
                    'GET',
                    "https://store.steampowered.com/api/appdetails?appids={$appid}&cc=ru&l=ru"
                );
                $detailsData = $detailsResponse->toArray();
            } catch (TransportExceptionInterface $e) {
                $output->writeln("<comment>⚠️ HTTP-ошибка для {$appid}: {$e->getMessage()}</comment>");
                continue;
            } catch (\Throwable) {
                $output->writeln("<comment>⚠️ Некорректный ответ для {$appid}. Пропускаем.</comment>");
                continue;
            }

            usleep(random_int(1000000, 2000000));

            $raw = $detailsData[$appid] ?? null;
            $success = $raw['success'] ?? false;
            $gameData = $raw['data'] ?? null;

            $steamApp = new SteamApp();
            $steamApp->setAppId($appid);
            $steamApp->setType($gameData['type'] ?? 'empty');
            $steamApp->setRawData((string)json_encode($raw, JSON_UNESCAPED_UNICODE));
            $this->entityManager->persist($steamApp);
            $this->entityManager->flush();

            if (!$success || empty($gameData)) {
                $output->writeln(
                    "<comment>" .
                    "⚠️ Приложение {$appid} пустое или не удалось загрузить. Сохраняем как type=empty.</comment>"
                );
                continue;
            }

            $output->writeln("✅ <info>Детали приложения {$appid} загружены и сохранены.</info>");

            if (
                ($gameData['type'] ?? '') !== 'game' ||
                empty($gameData['short_description']) ||
                empty($gameData['genres']) ||
                empty($gameData['price_overview'])
            ) {
                $output->writeln("<comment>⏩ Приложение {$appid} не является игрой.</comment>");
                continue;
            }

            // Проверяем, существует ли GameShop
            $existingGameShop = $this->entityManager
                ->getRepository(GameShop::class)
                ->findOneBy(['link_game_id' => $appid]);

            if ($existingGameShop) {
                $output->writeln("<comment>⏩ Приложение {$appid} уже связано с GameShop.</comment>");
                continue;
            }

            // Создаём или ищем Game
            $game = $this->entityManager
                ->getRepository(Game::class)
                ->findOneBy(['name' => $gameName]);

            if (!$game) {
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
                $imageUrl = $gameData['header_image'] ?? null;
                if ($imageUrl) {
                    try {
                        $imageContents = file_get_contents($imageUrl);
                        $imageName = uniqid('game_') . '.jpg';
                        $savePath = __DIR__ . '/../../public/uploads/games/' . $imageName;

                        if (!is_dir(dirname($savePath))) {
                            mkdir(dirname($savePath), 0777, true);
                        }

                        file_put_contents($savePath, $imageContents);
                        $game->setImage('/uploads/games/' . $imageName);
                    } catch (\Throwable) {
                        $output->writeln("<comment>⚠️ Не удалось сохранить изображение для {$appid}</comment>");
                    }
                }

                try {
                    $game->setReleaseDate(new \DateTime($gameData['release_date']['date'] ?? '2000-01-01'));
                } catch (\Exception) {
                    $game->setReleaseDate(new \DateTime('2000-01-01'));
                }

                $game->setCreatedAt(new \DateTimeImmutable());
                $game->setCreatedBy('system');
                $this->entityManager->persist($game);
            }

            // Жанры
            foreach ($gameData['genres'] as $genreItem) {
                $genreName = trim($genreItem['description']);

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
                    }

                    $genreCache[$genreName] = $genre;
                }

                if (!$game->getGenre()->contains($genre)) {
                    $game->addGenre($genre);
                }
            }

            // Сохраняем GameShop
            $gameShop = new GameShop();
            $gameShop->setGame($game);
            $gameShop->setShop($shopSteam);
            $gameShop->setLinkGameId($appid);
            $gameShop->setName($gameName);
            $gameShop->setLink("https://store.steampowered.com/app/{$appid}/");
            $this->entityManager->persist($gameShop);

            $imported++;
            if ($imported % $batchSize === 0) {
                $this->entityManager->flush();
                $output->writeln("📦 <info>Импортировано {$imported} игр на данный момент...</info>");
            }
        }

        $this->entityManager->flush();
        $output->writeln("🎉 <info>Импорт завершён! Всего игр импортировано: {$imported}</info>");

        return Command::SUCCESS;
    }
}
