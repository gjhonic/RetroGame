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
    name: 'app:steambuy-update-prices',
    description: 'Fetches current prices from SteamBuy and saves them to price history',
)]
class SteambayUpdatePricesCommand extends Command
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
        $output->writeln('🚀 Начинаем обновление цен SteamBuy...');

        $shop = $this->entityManager->getRepository(\App\Entity\Shop::class)->find(2);
        if (!$shop) {
            $output->writeln('<error>Магазин SteamBuy (id=2) не найден</error>');
            return Command::FAILURE;
        }

        $gameShops = $this->entityManager
            ->getRepository(GameShop::class)
            ->createQueryBuilder('gs')
            ->where('gs.shop = :shop')
            ->andWhere('gs.shouldImportPrice = true')
            ->setParameter('shop', $shop)
            ->getQuery()
            ->getResult();

        $total = count($gameShops);
        $output->writeln("🔍 Найдено игр для обновления: {$total}");

        $updated = 0;
        $checked = 0;

        foreach ($gameShops as $gameShop) {
            if ($checked >= 150) {
                $output->writeln('⛔ Достигнут лимит в 100 игр. Завершаем.');
                break;
            }

            $slug = $gameShop->getExternalKey();
            $url = "https://steambuy.com/steam/{$slug}/";

            $output->writeln("🌐 Запрос цены для '{$gameShop->getName()}', URL: $url");

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

            try {
                $start = microtime(true);
                $response = $this->httpClient->request('GET', $url, [
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0',
                    ]
                ]);
                $duration = round(microtime(true) - $start, 2);

                $checked++;

                $html = $response->getContent();

                if (preg_match('/<div class="product-price__cost">\s*(.*?)\s*<\/div>/', $html, $matches)) {
                    $priceText = trim($matches[1]);

                    if (preg_match('/^\d[\d\s]*\s*р$/u', $priceText)) {
                        // Цена в виде числа (например: "30" или "1 000")
                        $priceClean = str_replace(' ', '', $priceText);
                        $price = floatval($priceClean);

                        if ($price > 0) {
                            $history = new GameShopPriceHistory();
                            $history->setGameShop($gameShop);
                            $history->setPrice($price);
                            $history->setUpdatedAt(new \DateTime());

                            $this->entityManager->persist($history);
                            $output->writeln("✅ Цена {$price} ₽ получена за {$duration} сек.");
                            $updated++;
                        } else {
                            $output->writeln("⚠ Цена равна 0, не сохраняем.");
                        }
                    } elseif (mb_strtolower($priceText) === 'скоро') {
                        // Товар временно нет в продаже, НЕ отключаем импорт
                        $output->writeln(
                            "ℹ Товар временно отсутствует (Скоро), пропускаем, импорт оставлен включенным."
                        );
                    } else {
                        // Неизвестный формат цены
                        $output->writeln("❌ Неизвестный формат цены: '{$priceText}', отключаем импорт для игры.");
                        $gameShop->setShouldImportPrice(false);
                        $this->entityManager->persist($gameShop);
                    }
                } else {
                    $output->writeln("❌ Цена не найдена, отключаем импорт для игры.");
                    $gameShop->setShouldImportPrice(false);
                    $this->entityManager->persist($gameShop);
                }

                usleep(1500000); // 1.5 секунды пауза между запросами
            } catch (\Throwable $e) {
                $output->writeln("<error>Ошибка при запросе: {$e->getMessage()}</error>");
            }

            $this->entityManager->flush();
        }

        $output->writeln("🎉 Цены обновлены для {$updated} игр из {$checked} проверенных.");

        return Command::SUCCESS;
    }
}
