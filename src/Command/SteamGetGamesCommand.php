<?php

namespace App\Command;

use App\Entity\Game;
use App\Entity\GameShop;
use App\Entity\Genre;
use App\Entity\Shop;
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
        $output->writeln('Fetching games list from Steam...');

        try {
            $response = $this->httpClient->request('GET', 'https://api.steampowered.com/ISteamApps/GetAppList/v2/');
            $data = $response->toArray();
        } catch (\Throwable $e) {
            $output->writeln('<error>Failed to fetch games list: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $apps = $data['applist']['apps'] ?? [];
        $output->writeln('Total apps found: ' . count($apps));

        $shopSteam = $this->entityManager
            ->getRepository(Shop::class)
            ->find(1);

        if (!$shopSteam) {
            $output->writeln('<error>Steam shop (id=1) not found in DB.</error>');
            return Command::FAILURE;
        }

        $batchSize = 10;
        $i = 0;
        $imported = 0;
        $genreCache = [];

        $count = 0;

        foreach ($apps as $app) {
            $count++;

            $appid = $app['appid'] ?? null;
            $gameName = trim($app['name'] ?? '');

            if (!$appid || !$gameName) {
                continue;
            }

            if ($count === 30) {
                $output->writeln("Обработано 30 записей ждём 20 секунд");
                usleep(20000000);
                $count = 0;
            }

            // Получаем подробную информацию об игре
            try {
                $detailsResponse = $this->httpClient->request(
                    'GET',
                    "https://store.steampowered.com/api/appdetails?appids={$appid}&cc=us&l=ru"
                );
                $detailsData = $detailsResponse->toArray();

            } catch (TransportExceptionInterface $e) {
                $output->writeln(
                    "<comment>Skipping app {$appid} due to HTTP error: {$e->getMessage()}</comment>"
                );
                continue;
            } catch (\Throwable $e) {
                $output->writeln("<comment>Invalid response for app {$appid}. Skipping.</comment>");
                continue;
            }

            if (
                !isset($detailsData[$appid]['success']) ||
                !$detailsData[$appid]['success'] ||
                empty($detailsData[$appid]['data'])
            ) {
                continue;
            }

            $gameData = $detailsData[$appid]['data'];

            if (
                $gameData['type'] !== 'game' ||
                empty($gameData['short_description']) ||
                empty($gameData['genres']) ||
                empty($gameData['price_overview'])
            ) {
                continue;
            }

            // Проверяем, не добавлена ли уже такая игра в GameShop
            $existing = $this->entityManager
                ->getRepository(GameShop::class)
                ->findOneBy(['link_game_id' => $appid]);

            if ($existing) {
                continue;
            }

            // Проверяем, существует ли игра
            $game = $this->entityManager
                ->getRepository(Game::class)
                ->findOneBy(['name' => $gameName]);

            if (!$game) {
                $game = new Game();
                $game->setName($gameName);
                $game->setDescription($gameData['short_description']);
                $game->setIsFree(empty($gameData['is_free']) ? false : true);

                // Скачиваем и сохраняем изображение
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
                        $game->setImage('/uploads/games/' . $imageName); // путь, который можно использовать в Twig
                    } catch (\Throwable $e) {
                        $output->writeln("<comment>Не удалось сохранить изображение для {$appid}</comment>");
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

            // Обработка жанров
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

            $gameShop = new GameShop();
            $gameShop->setGame($game);
            $gameShop->setShop($shopSteam);
            $gameShop->setLinkGameId($appid);
            $gameShop->setName($gameName);
            $gameShop->setLink("https://store.steampowered.com/app/{$appid}/");

            $this->entityManager->persist($gameShop);

            $imported++;
            if (++$i % $batchSize === 0) {
                $this->entityManager->flush();
                $output->writeln("Imported {$imported} games so far...");
            }

            usleep(3000000);
        }

        $this->entityManager->flush();
        $output->writeln("Finished! Total games imported: {$imported}");

        return Command::SUCCESS;
    }
}
