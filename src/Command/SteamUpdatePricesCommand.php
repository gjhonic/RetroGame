<?php

namespace App\Command;

use App\Entity\GameShop;
use App\Entity\GameShopPriceHistory;
use App\Entity\LogCron;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:steam-update-prices',
    description: 'Fetches current prices from Steam and saves them to price history',
)]
class SteamUpdatePricesCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private HttpClientInterface $httpClient;

    public function __construct(EntityManagerInterface $entityManager, HttpClientInterface $httpClient)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->httpClient = $httpClient;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startTime = microtime(true);

        // --- Логирование старта ---
        $logsCron = new LogCron();
        $logsCron->setCronName('steam-update-prices');
        $logsCron->setDatetimeStart(new \DateTime());
        $this->entityManager->persist($logsCron);
        $this->entityManager->flush();

        $now = new \DateTime();
        $output->writeln('🚀 <info>Начинаем обновление цен Steam...</info>');
        $output->writeln('📅 <info>' . $now->format('Y-m-d H:i:s') . '</info>');

        $steamGames = $this->entityManager
            ->getRepository(GameShop::class)
            ->createQueryBuilder('gs')
            ->join('gs.game', 'g')
            ->where('gs.shop = :shop')
            ->andWhere('gs.shouldImportPrice = true')
            ->andWhere('g.isFree = false')
            ->setParameter('shop', 1)
            ->getQuery()
            ->getResult();

        $total = count($steamGames);
        $output->writeln("🔍 <info>Найдено игр для обновления: {$total}</info>");

        $updated = 0;
        $checked = 0;
        $batchSize = 50; // Размер пачки для сохранения
        $batch = [];

        $startOfDay = (new \DateTime())->setTime(0, 0, 0);
        $endOfDay = (new \DateTime())->setTime(23, 59, 59);

        $existingGameShops = $this->entityManager
            ->getRepository(GameShopPriceHistory::class)
            ->createQueryBuilder('h')
            ->select('IDENTITY(h.gameShop) AS gameShopId')
            ->where('h.updatedAt BETWEEN :start AND :end')
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->groupBy('h.gameShop')
            ->getQuery()
            ->getArrayResult();

        // Преобразуем в простой массив ID
        $alreadyUpdatedIds = array_column($existingGameShops, 'gameShopId');

        foreach ($steamGames as $gameShop) {
            if ($checked >= 1500) {
                $output->writeln('⏹️ <comment>Достигнут лимит в 1500 игр. Завершаем.</comment>');
                break;
            }

            $game = $gameShop->getGame();

            if (in_array($gameShop->getId(), $alreadyUpdatedIds)) {
                $output->writeln(
                    '🔄 <comment>' .
                    "[{$gameShop->getLinkGameId()}] {$gameShop->getName()} — Цена уже есть на сегодня, пропускаем." .
                        '</comment>'
                );
                continue;
            }

            usleep(random_int(1000000, 1500000));

            $appid = $gameShop->getLinkGameId();
            $url = "https://store.steampowered.com/app/{$appid}/?cc=ru";

            try {
                $output->writeln("🌐 <info>[{$appid}] Отправляем запрос по URL: {$url}</info>");

                $response = $this->httpClient->request('GET', $url, [
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0',
                    ],
                ]);

                ++$checked;

                $html = $response->getContent();

                // 1. Пробуем найти цену со скидкой
                if (preg_match('/<div class=\"discount_final_price\">([^<]+)<\/div>/s', $html, $matches)) {
                    $priceText = strip_tags(trim($matches[1]));
                    $output->writeln("💸 <info>[{$appid}] Найдена цена со скидкой: $priceText</info>");
                } elseif (
                    preg_match('/<div class=\"game_purchase_price price\"[^>]*>(.*?)<\/div>/s', $html, $matches)
                ) {
                    $priceText = strip_tags(trim($matches[1]));
                    $output->writeln("💰 <info>[{$appid}] Найдена обычная цена: $priceText</info>");
                } else {
                    $output->writeln("❌ <comment>[{$appid}] Цена не найдена. Отключаем импорт.</comment>");
                    $gameShop->setShouldImportPrice(false);
                    $this->entityManager->persist($gameShop);
                    $this->entityManager->flush();
                    continue;
                }

                // 2. Проверка валюты
                if (!str_contains($priceText, '₽') && !str_contains(mb_strtolower($priceText), 'руб')) {
                    $output->writeln(
                        '🚫 <comment>' .
                        "[{$appid}] Цена в неподдерживаемой валюте: {$priceText}. Пропускаем.</comment>"
                    );
                    continue;
                }

                // 3. Очистка и конвертация
                $cleaned = str_replace(['₽', 'руб.', 'руб', ' '], '', mb_strtolower($priceText));
                $cleaned = str_replace(',', '.', $cleaned);
                $price = floatval($cleaned);

                // 4. Сохраняем, если цена > 0
                if ($price > 0) {
                    $history = new GameShopPriceHistory();
                    $history->setGameShop($gameShop);
                    $history->setPrice($price);
                    $history->setUpdatedAt(new \DateTime());

                    $this->entityManager->persist($history);
                    $batch[] = $history;
                    $output->writeln("✅ <info>[{$appid}] {$gameShop->getName()} — {$price} ₽</info>");
                    ++$updated;
                } else {
                    $output->writeln("⚠️ <comment>[{$appid}] Цена равна 0, не сохраняем.</comment>");
                }

                // Сохраняем пачкой
                if (count($batch) >= $batchSize) {
                    $this->entityManager->flush();
                    $batch = [];
                    $output->writeln("💾 <info>Сохранена пачка из {$batchSize} записей</info>");
                }
            } catch (\Throwable $e) {
                $output->writeln("<error>⛔ [{$appid}] Ошибка при запросе: {$e->getMessage()}</error>");
            }
        }

        // Сохраняем оставшиеся записи
        if (!empty($batch)) {
            $this->entityManager->flush();
            $output->writeln("💾 <info>Сохранена финальная пачка из " . count($batch) . " записей</info>");
        }

        $output->writeln("🎉 <info>Цены обновлены для {$updated} игр из {$checked} проверенных.</info>");

        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        $output->writeln(sprintf('⏱️ <info>Время выполнения: %.2f секунд</info>', $duration));

        // --- Логирование окончания ---
        $logsCron->setDatetimeEnd(new \DateTime());
        $logsCron->setWorkTime($duration);
        $logsCron->setMaxMemorySize(round(memory_get_peak_usage(true) / 1024 / 1024, 2));
        $this->entityManager->persist($logsCron);
        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}
