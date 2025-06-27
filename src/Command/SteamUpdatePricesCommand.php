<?php

namespace App\Command;

use App\Entity\GameShop;
use App\Entity\GameShopPriceHistory;
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
        $now = new \DateTime();
        $output->writeln('📅 ' . $now->format('Y-m-d H:i:s'));
        $output->writeln('🚀 Начинаем обновление цен Steam...');

        $steamGames = $this->entityManager
            ->getRepository(GameShop::class)
            ->createQueryBuilder('gs')
            ->where('gs.shop = :shop')
            ->andWhere('gs.shouldImportPrice = true')
            ->setParameter('shop', 1)
            ->getQuery()
            ->getResult();

        $total = count($steamGames);
        $output->writeln("🔍 Найдено игр для обновления: {$total}");

        $updated = 0;
        $checked = 0;

        foreach ($steamGames as $index => $gameShop) {
            if ($checked >= 150) {
                $output->writeln('⛔ Достигнут лимит в 150 игр. Завершаем.');
                break;
            }

            $game = $gameShop->getGame();

            if ($game && $game->isFree()) {
                $output->writeln(
                    "⏩ [{$gameShop->getLinkGameId()}] {$gameShop->getName()} — Бесплатная игра, пропускаем."
                );
                continue;
            }

            $startOfDay = (new \DateTime())->setTime(0, 0, 0);
            $endOfDay = (new \DateTime())->setTime(23, 59, 59);

            $existing = $this->entityManager
                ->getRepository(GameShopPriceHistory::class)
                ->createQueryBuilder('h')
                ->select('COUNT(h.id)')
                ->where('h.gameShop = :gameShop')
                ->andWhere('h.updatedAt >= :startOfDay')
                ->andWhere('h.updatedAt <= :endOfDay')
                ->setParameter('gameShop', $gameShop)
                ->setParameter('startOfDay', $startOfDay)
                ->setParameter('endOfDay', $endOfDay)
                ->getQuery()
                ->getSingleScalarResult();

            if ($existing > 0) {
                $output->writeln(
                    "🔁 [{$gameShop->getLinkGameId()}] {$gameShop->getName()} — Цена уже есть на сегодня, пропускаем."
                );
                continue;
            }

            $appid = $gameShop->getLinkGameId();
            $url = "https://store.steampowered.com/app/{$appid}/?cc=ru";

            try {
                $output->writeln("🌐 [$appid] Отправляем запрос по URL: {$url}");

                $response = $this->httpClient->request('GET', $url, [
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0',
                    ]
                ]);

                $checked++;

                $html = $response->getContent();


                // 1. Пробуем найти цену со скидкой
                if (preg_match('/<div class="discount_final_price">([^<]+)<\/div>/s', $html, $matches)) {
                    $priceText = strip_tags(trim($matches[1]));
                    $output->writeln("💸 [$appid] Найдена цена со скидкой: $priceText");
                } elseif (preg_match('/<div class="game_purchase_price price"[^>]*>(.*?)<\/div>/s', $html, $matches)) {
                    $priceText = strip_tags(trim($matches[1]));
                    $output->writeln("💰 [$appid] Найдена обычная цена: $priceText");
                } else {
                    $output->writeln("❌ [$appid] Цена не найдена. Отключаем импорт.");
                    $gameShop->setShouldImportPrice(false);
                    $this->entityManager->persist($gameShop);
                    $this->entityManager->flush();
                    continue;
                }

                // Очистка и конвертация
                $cleaned = str_replace(['₽', 'руб.', ' '], '', $priceText);
                $cleaned = str_replace(',', '.', $cleaned);
                $price = floatval($cleaned);

                // Сохраняем, если цена > 0
                if ($price > 0) {
                    $history = new GameShopPriceHistory();
                    $history->setGameShop($gameShop);
                    $history->setPrice($price);
                    $history->setUpdatedAt(new \DateTime());

                    $this->entityManager->persist($history);
                    $output->writeln("✅ [$appid] {$gameShop->getName()} — {$price} ₽");
                    $updated++;
                } else {
                    $output->writeln("✘ [$appid] Цена равна 0, не сохраняем.");
                }


                usleep(2000000); // Пауза 1.5 секунды
            } catch (\Throwable $e) {
                $output->writeln("⚠ [$appid] Ошибка при запросе: {$e->getMessage()}");
            }
            $this->entityManager->flush();
        }

        $this->entityManager->flush();
        $output->writeln("🎉 Цены обновлены для {$updated} игр из {$checked} проверенных.");

        return Command::SUCCESS;
    }
}
