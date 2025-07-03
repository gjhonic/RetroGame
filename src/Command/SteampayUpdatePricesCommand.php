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
    name: 'app:steampay-update-prices',
    description: 'Fetches current prices from SteamPay and saves them to price history',
)]
class SteampayUpdatePricesCommand extends Command
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

        $shop = $this->entityManager->getRepository(\App\Entity\Shop::class)->find(3);
        if (!$shop) {
            $output->writeln('<error>⛔ Магазин SteamPay (id=3) не найден</error>');

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
            $url = "https://steampay.com/game/{$slug}/";

            $output->writeln("🌐 <info>Запрос цены для '{$gameShop->getName()}', URL: $url</info>");

            if (in_array($gameShop->getId(), $alreadyUpdatedIds)) {
                $output->writeln(
                    '🔄 <comment> ' .
                    "[{$gameShop->getLinkGameId()}] {$gameShop->getName()} — Цена уже есть на сегодня, пропускаем." .
                    '</comment>'
                );
                continue;
            }

            usleep(random_int(1000000, 2000000));

            try {
                $start = microtime(true);
                $response = $this->httpClient->request('GET', $url, [
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0',
                    ],
                ]);
                $duration = round(microtime(true) - $start, 2);

                ++$checked;

                $html = $response->getContent();

                $extraParams = $gameShop->getExtraParams();

                // Попытка найти параметр "Наличие" из <ul class="product__advantages-list">
                if (
                    preg_match_all(
                        '#<li[^>]*class="product__advantages-item[^"]*--[^"]*available[^"]*"[^>]*>\s*Наличие:\s*' .
                        '(?:<span[^>]*class="product__advantages-(\w+)"[^>]*>)?([^<]+)(?:</span>)?#su',
                        $html,
                        $matches,
                        PREG_SET_ORDER
                    )
                ) {
                    foreach ($matches as $match) {
                        $value = trim($match[2]);
                        $type = $this->getMapTypePrice($value);

                        $extraParams['paramPrice'] = [
                            'type' => $type,
                            'value' => $value,
                        ];
                        break;
                    }
                }

                $gameShop->setExtraParams($extraParams);

                if (preg_match('/<div class="product__current-price">(.*?)<\/div>/s', $html, $matches)) {
                    $priceBlock = trim(strip_tags($matches[1]));
                    $priceText = preg_replace('/\s+/', ' ', $priceBlock); // убираем лишние пробелы

                    // Удаляем 'руб.' или 'руб' (на всякий случай)
                    $priceText = preg_replace('/руб\.?/ui', '', (string) $priceText);
                    $priceText = trim((string) $priceText);

                    if ('скоро' === mb_strtolower($priceText)) {
                        $output->writeln(
                            'ℹ️ <comment> ' .
                            'Товар временно отсутствует (Скоро), пропускаем, импорт оставлен включённым.</comment>'
                        );
                    } elseif (preg_match('/^\d[\d\s]*$/u', $priceText)) {
                        $priceClean = str_replace(' ', '', $priceText);
                        $price = floatval($priceClean);

                        if ($price > 0) {
                            $history = new GameShopPriceHistory();
                            $history->setGameShop($gameShop);
                            $history->setPrice($price);
                            $history->setUpdatedAt(new \DateTime());

                            $this->entityManager->persist($history);
                            $output->writeln("✅ <info>Цена {$price} ₽ получена за {$duration} сек.</info>");
                            ++$updated;
                        } else {
                            $output->writeln('⚠️ <comment>Цена равна 0, не сохраняем.</comment>');
                        }
                    } else {
                        $output->writeln('❌ <comment> ' .
                            "Неизвестный формат цены: '{$priceText}', отключаем импорт для игры.</comment>");
                        $gameShop->setShouldImportPrice(false);
                        $this->entityManager->persist($gameShop);
                    }
                } else {
                    $output->writeln('❌ <comment>Цена не найдена, отключаем импорт для игры.</comment>');
                    $gameShop->setShouldImportPrice(false);
                    $this->entityManager->persist($gameShop);
                }
            } catch (\Throwable $e) {
                if (404 == $e->getCode()) {
                    $output->writeln('❌ <comment>Цена не найдена, отключаем импорт для игры.</comment>');
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

    public function getMapTypePrice(string $value): string
    {
        switch ($value) {
            case 'мало':
                return 'warning';
            case 'много':
                return 'success';
            case 'Достаточно':
                return 'primary';
            case 'закончился':
                return 'danger';
            case 'ожидается':
                return 'danger';
        }

        return 'dark';
    }
}
