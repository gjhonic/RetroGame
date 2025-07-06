<?php

namespace App\Command;

use App\Entity\Game;
use App\Entity\GameShop;
use App\Entity\LogCron;
use App\Entity\SteamApp;
use App\Service\SteamGameDataProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Команда импорта игр из Steam.
 *
 * Системное имя: app:steam-get-games
 *
 * Назначение:
 *   Импортирует список всех доступных игр из Steam в локальную базу данных.
 *
 * Логика работы:
 *   - Получает список игр через Steam API (GetAppList/v2).
 *   - Для каждой игры:
 *       - Проверяет, есть ли она уже в базе.
 *       - Если нет — добавляет новую запись в SteamApp.
 *       - Получает подробную информацию через API appdetails.
 *       - Обрабатывает данные через SteamGameDataProcessor.
 *   - Ограничивает обработку 300 играми за запуск.
 *   - Делает паузы между запросами для избежания блокировки.
 *
 * Логирование:
 *   - Фиксирует начало и конец выполнения.
 *   - Записывает количество импортированных игр.
 *   - Логирует время работы и пиковое потребление памяти.
 *   - Логирует ошибки при получении или сохранении данных.
 *
 * Использование:
 *   php bin/console app:steam-get-games
 *
 * Особенности:
 *   - Использует оптимизацию с предзагрузкой существующих данных.
 *   - Обрабатывает данные пакетно для оптимизации производительности.
 *   - Делает случайные паузы между запросами (1-2 секунды).
 *   - Пауза 7-10 секунд каждые 30 обработанных игр.
 */
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
        $startTime = microtime(true);

        $output->writeln('🚀 <info>Начинаем загрузку списка приложений Steam...</info>');

        // --- Логирование старта ---
        $logsCron = new LogCron();
        $logsCron->setCronName('steam-get-games');
        $logsCron->setDatetimeStart(new \DateTime());
        $this->entityManager->persist($logsCron);
        $this->entityManager->flush();

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

        foreach ($apps as $app) {
            if ($processedCount >= 100) {
                $output->writeln('⏹️ <comment>Достигнут лимит 100 обработанных игр. Останавливаем импорт.</comment>');
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

            if (30 === $checked) {
                $this->entityManager->flush();
                $output->writeln('⏳ <info>Обработано 30 приложений, пауза 10 секунд...</info>');

                usleep(random_int(7000000, 10000000));
                $checked = 0;
            }

            ++$checked;

            // Получаем подробную информацию
            try {
                $detailsResponse = $this->httpClient->request('GET', 'https://store.steampowered.com/api/appdetails', [
                    'query' => [
                        'appids' => $appid,
                        'l' => 'russian',
                    ],
                ]);
                $detailsData = $detailsResponse->toArray();
            } catch (TransportExceptionInterface $e) {
                $output->writeln("<comment>⚠️ HTTP-ошибка для {$appid}: {$e->getMessage()}</comment>");
                continue;
            } catch (\Throwable) {
                $output->writeln("<comment>⚠️ Некорректный ответ для {$appid}. Пропускаем.</comment>");
                continue;
            }

            ++$processedCount;

            usleep(random_int(1000000, 2000000));

            $steamApp = new SteamApp();
            $steamApp->setAppId($appid);
            $steamApp->setName($gameName);
            $steamApp->setType($detailsData[$appid]['data']['type'] ?? 'empty');
            $steamApp->setRawData((string) json_encode($detailsData, JSON_UNESCAPED_UNICODE));
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
                ++$imported;
            }
        }

        $this->entityManager->flush();
        $output->writeln("🎉 <info>Импорт завершён! Всего игр импортировано: {$imported}</info>");

        // --- Логирование окончания ---
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        $logsCron->setDatetimeEnd(new \DateTime());
        $logsCron->setWorkTime($duration);
        $logsCron->setMaxMemorySize(round(memory_get_peak_usage(true) / 1024 / 1024, 2));
        $this->entityManager->persist($logsCron);
        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}
