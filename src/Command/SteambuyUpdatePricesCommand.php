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
class SteambuyUpdatePricesCommand extends Command
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
        $output->writeln('🚀 <info>Начинаем обновление цен SteamBuy...</info>');
        $output->writeln('📅 <info>' . $now->format('Y-m-d H:i:s') . '</info>');

        $shop = $this->entityManager->getRepository(\App\Entity\Shop::class)->find(2);
        if (!$shop) {
            $output->writeln('<error>⛔ Магазин SteamBuy (id=2) не найден</error>');
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
        $output->writeln("🔍 <info>Найдено игр для обновления: {$total}</info>");

        $updated = 0;
        $checked = 0;

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

        $alreadyUpdatedIds = array_column($existingGameShops, 'gameShopId');

        foreach ($gameShops as $gameShop) {
            if ($checked >= 1000) {
                $output->writeln('⏹️ <comment>Достигнут лимит в 1000 игр. Завершаем.</comment>');
                break;
            }

            $slug = $gameShop->getExternalKey();
            $url = "https://steambuy.com/steam/{$slug}/";

            $output->writeln("🌐 <info>Запрос цены для '{$gameShop->getName()}', URL: $url</info>");

            if (in_array($gameShop->getId(), $alreadyUpdatedIds)) {
                $output->writeln(
                    "🔄 <comment> " .
                    "[{$gameShop->getLinkGameId()}] {$gameShop->getName()} — Цена уже есть на сегодня, пропускаем." .
                    "</comment>"
                );
                continue;
            }

            usleep(random_int(1000000, 2000000));

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

                if (preg_match('/<div class=\"product-price__cost\">\s*(.*?)\s*<\/div>/', $html, $matches)) {
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
                            $output->writeln("✅ <info>Цена {$price} ₽ получена за {$duration} сек.</info>");
                            $updated++;
                        } else {
                            $output->writeln("⚠️ <comment>Цена равна 0, не сохраняем.</comment>");
                        }
                    } elseif (mb_strtolower($priceText) === 'скоро') {
                        // Товар временно нет в продаже, НЕ отключаем импорт
                        $output->writeln(
                            "ℹ️ <comment>" .
                            "Товар временно отсутствует (Скоро), пропускаем, импорт оставлен включённым.</comment>"
                        );
                    } else {
                        // Неизвестный формат цены
                        $output->writeln("❌ <comment> " .
                            "Неизвестный формат цены: '{$priceText}', отключаем импорт для игры.</comment>");
                        $gameShop->setShouldImportPrice(false);
                        $this->entityManager->persist($gameShop);
                    }
                } else {
                    $output->writeln("❌ <comment>Цена не найдена, отключаем импорт для игры.</comment>");
                    $gameShop->setShouldImportPrice(false);
                    $this->entityManager->persist($gameShop);
                }
            } catch (\Throwable $e) {
                if ($e->getCode() == 404) {
                    $output->writeln("❌ <comment>Цена не найдена, отключаем импорт для игры.</comment>");
                    $gameShop->setShouldImportPrice(false);
                    $this->entityManager->persist($gameShop);
                } else {
                    $output->writeln("<error>⛔ Ошибка при запросе: {$e->getMessage()}</error>");
                }
            }

            $this->entityManager->flush();
        }

        $output->writeln("🎉 <info>Цены обновлены для {$updated} игр из {$checked} проверенных.</info>");

        return Command::SUCCESS;
    }
}
