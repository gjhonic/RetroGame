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

/**
 * Команда обновления цен на игры IGM.gg.
 *
 * Системное имя: app:igm-update-prices
 *
 * Назначение:
 *   Получает актуальные цены на игры из IGM.gg и сохраняет их в историю цен.
 *
 * Логика работы:
 *   - Находит все игры IGM.gg, для которых требуется обновление цены (shop=IGM.gg, shouldImportPrice=true).
 *   - Для каждой игры:
 *       - Проверяет, обновлялась ли цена сегодня.
 *       - Получает страницу игры с сайта IGM.gg.
 *       - Извлекает цену из блока Price_price__price-text__MpdHL.
 *       - Проверяет наличие товара и тип цены.
 *       - Сохраняет цену в историю, если она > 0.
 *   - Ограничивает обработку 1000 играми за запуск.
 *   - Делает паузы между запросами для избежания блокировки.
 *
 * Логирование:
 *   - Фиксирует начало и конец выполнения, время работы, пиковое потребление памяти.
 *   - Записывает количество обновлённых и проверенных игр.
 *   - Логирует ошибки и пропуски (например, если цена не найдена или товар отсутствует).
 *
 * Использование:
 *   php bin/console app:igm-update-prices
 *
 * Особенности:
 *   - Обрабатывает специальные случаи: "скоро" (товар временно отсутствует).
 *   - Извлекает дополнительную информацию о наличии товара.
 *   - Отключает импорт цен для игр, где цена не найдена или имеет неизвестный формат.
 *   - Делает случайные паузы между запросами (1-2 секунды).
 *   - Проверяет наличие цены за сегодня перед обработкой.
 */
#[AsCommand(
    name: 'app:igm-update-prices',
    description: 'Fetches current prices from IGM.gg and saves them to price history',
)]
class IgmUpdatePricesCommand extends Command
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

        $now = new \DateTime();
        $output->writeln('🚀 <info>Начинаем обновление цен IGM.gg...</info>');
        $output->writeln('📅 <info>' . $now->format('Y-m-d H:i:s') . '</info>');

        // --- Логирование старта ---
        $logsCron = new LogCron();
        $logsCron->setCronName('igm-update-prices');
        $logsCron->setDatetimeStart(new \DateTime());
        $this->entityManager->persist($logsCron);
        $this->entityManager->flush();

        $shop = $this->entityManager->getRepository(\App\Entity\Shop::class)->find(5);
        if (!$shop) {
            $output->writeln('<error>⛔ Магазин IGM.gg (id=5) не найден</error>');

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
            $url = "https://igm.gg/game/{$slug}/";

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

                // Извлекаем цену из блока Price_price__price-text__MpdHL
                if (
                    preg_match(
                        '/<p[^>]*class="[^"]*Price_price__price-text__MpdHL[^"]*"[^>]*>(.*?)<\/p>/s',
                        $html,
                        $matches
                    )
                ) {
                    $priceBlock = trim(strip_tags($matches[1]));

                    // Декодируем HTML-сущности (например, &nbsp;)
                    $priceBlock = html_entity_decode($priceBlock, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                    // Заменяем все виды пробелов на обычные пробелы
                    $priceBlock = preg_replace('/[\s\x{00A0}\x{2009}\x{202F}]+/u', ' ', $priceBlock);

                    $priceText = preg_replace('/\s+/', ' ', $priceBlock); // убираем лишние пробелы

                    // Удаляем '₽' и другие символы валюты
                    $priceText = preg_replace('/[₽₴$€£¥]/ui', '', (string) $priceText);
                    $priceText = trim((string) $priceText);

                    if ('скоро' === mb_strtolower($priceText)) {
                        $output->writeln(
                            'ℹ️ <comment> ' .
                            'Товар временно отсутствует (Скоро), пропускаем, импорт оставлен включённым.</comment>'
                        );
                    } elseif (preg_match('/^[\d\s]+$/u', $priceText)) {
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
