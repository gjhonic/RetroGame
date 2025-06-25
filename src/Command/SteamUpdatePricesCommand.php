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
        $output->writeln('Starting price update from Steam...');

        // Получаем все GameShop с привязкой к Steam (Shop ID = 1)
        $steamGames = $this->entityManager
            ->getRepository(GameShop::class)
            ->findBy(['shop' => 1]);

        $updated = 0;

        foreach ($steamGames as $gameShop) {
            $appid = $gameShop->getLinkGameId();
            $url = "https://store.steampowered.com/app/{$appid}/?cc=ru";

            try {
                $response = $this->httpClient->request('GET', $url, [
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0',
                    ]
                ]);

                $html = $response->getContent();

                if (preg_match('/<div class="discount_final_price">([^<]+)<\/div>/', $html, $matches)) {
                    $priceText = trim($matches[1]);

                    // Убираем символы, оставляем только цифры и запятую
                    $cleaned = str_replace(['₽', 'руб.', ' '], '', $priceText);
                    $cleaned = str_replace(',', '.', $cleaned);

                    $price = floatval($cleaned);

                    if ($price > 0) {
                        $history = new GameShopPriceHistory();
                        $history->setGameShop($gameShop);
                        $history->setPrice($price);
                        $history->setUpdatedAt(new \DateTime());

                        $this->entityManager->persist($history);

                        $output->writeln("✔ [{$appid}] {$gameShop->getName()} — {$price} руб.");
                        $updated++;
                    }
                } else {
                    $output->writeln("✘ [{$appid}] Цена не найдена.");
                }

                // Не спамим Steam
                usleep(1000000); // 1 сек
            } catch (\Throwable $e) {
                $output->writeln("⚠ Ошибка при запросе {$url}: {$e->getMessage()}");
            }
        }

        $this->entityManager->flush();
        $output->writeln("✅ Обновлено цен: {$updated}");

        return Command::SUCCESS;
    }
}
