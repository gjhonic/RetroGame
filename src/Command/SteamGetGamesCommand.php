<?php

namespace App\Command;

use App\Entity\Game;
use App\Entity\GameShop;
use App\Entity\SteamApp;
use App\Service\SteamGameDataProcessor;
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
        private readonly SteamGameDataProcessor $gameDataProcessor,
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

        // Получаем заранее все существующие ID для оптимизации
        $output->writeln('📊 <info>Загружаем существующие данные для оптимизации...</info>');

        // Получаем все существующие app_id из SteamApp
        $existingSteamAppIds = $this->entityManager
            ->getRepository(SteamApp::class)
            ->createQueryBuilder('sa')
            ->select('sa.app_id')
            ->getQuery()
            ->getSingleColumnResult();
        $existingSteamAppIds = array_flip($existingSteamAppIds);

        // Получаем все существующие link_game_id из GameShop
        $existingGameShopIds = $this->entityManager
            ->getRepository(GameShop::class)
            ->createQueryBuilder('gs')
            ->select('gs.link_game_id')
            ->where('gs.link_game_id IS NOT NULL')
            ->getQuery()
            ->getSingleColumnResult();
        $existingGameShopIds = array_flip($existingGameShopIds);

        // Получаем все существующие имена игр
        $existingGameNames = $this->entityManager
            ->getRepository(Game::class)
            ->createQueryBuilder('g')
            ->select('g.name')
            ->getQuery()
            ->getSingleColumnResult();
        $existingGameNames = array_flip($existingGameNames);

        $output->writeln(sprintf(
            '📈 <info>Найдено существующих: SteamApp=%d, GameShop=%d, Game=%d</info>',
            count($existingSteamAppIds),
            count($existingGameShopIds),
            count($existingGameNames)
        ));

        $imported = 0;
        $processedCount = 0;
        $checked = 0;
        $batchSize = 10;

        foreach ($apps as $app) {
            if ($processedCount >= 300) {
                $output->writeln('⏹️ <comment>Достигнут лимит 300 обработанных игр. Останавливаем импорт.</comment>');
                break;
            }

            $appid = $app['appid'] ?? null;
            $gameName = trim($app['name'] ?? '');

            if (!$appid || !$gameName) {
                continue;
            }

            // Уже обработано? (используем предзагруженный список)
            if (isset($existingSteamAppIds[$appid])) {
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

            $processedCount++;

            usleep(random_int(1000000, 2000000));

            $steamApp = new SteamApp();
            $steamApp->setAppId($appid);
            $steamApp->setType($detailsData[$appid]['data']['type'] ?? 'empty');
            $steamApp->setRawData((string)json_encode($detailsData, JSON_UNESCAPED_UNICODE));
            $this->entityManager->persist($steamApp);
            $this->entityManager->flush();

            // Обрабатываем данные игры через сервис
            $processed = $this->gameDataProcessor->processGameData(
                $detailsData,
                $output,
                $existingGameNames,
                $existingGameShopIds
            );

            if ($processed) {
                $imported++;
                if ($imported % $batchSize === 0) {
                    $this->entityManager->flush();
                    $output->writeln("📦 <info>Импортировано {$imported} игр на данный момент...</info>");
                }
            }
        }

        $this->entityManager->flush();
        $output->writeln("🎉 <info>Импорт завершён! Всего игр импортировано: {$imported}</info>");

        return Command::SUCCESS;
    }
}
