<?php

namespace App\Command;

use App\Entity\Game;
use App\Entity\GameShop;
use App\Entity\Shop;
use App\Entity\SteampayApp;
use App\Service\SlugifyProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:steampay-get-games',
    description: 'Links games with SteamPay if a valid page exists.',
)]
class SteampayGetGamesCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $games = $this->entityManager->getRepository(Game::class)->findAll();
        $shop = $this->entityManager->getRepository(Shop::class)->findOneBy(['id' => 3]);

        if (!$shop) {
            $output->writeln('<error>⛔ Магазин с ID 3 не найден</error>');

            return Command::FAILURE;
        }

        $output->writeln(sprintf('🚀 <info>Начинаем импорт для %d игр...</info>', count($games)));

        $imported = 0;
        $skippedExistingShop = 0;
        $skippedNotFound = 0;
        $errorsCount = 0;

        foreach ($games as $game) {
            if ($imported >= 200) {
                $output->writeln('⏹️ <comment>Достигнут лимит 200 импортированных игр. Останавливаем.</comment>');
                break;
            }

            $slug = SlugifyProcessor::process((string) $game->getName());
            $url = "https://steampay.com/game/{$slug}/";

            $output->writeln("🎮 <info>Обрабатываем игру: '{$game->getName()}', slug: $slug</info>");

            // Проверка существования GameShop
            $existingShop = $this->entityManager->getRepository(GameShop::class)->findOneBy([
                'game' => $game,
                'shop' => $shop,
            ]);

            if ($existingShop) {
                ++$skippedExistingShop;
                continue;
            }

            // Проверка SteampayApp
            $SteampayApp = $this->entityManager->getRepository(SteampayApp::class)->findOneBy([
                'slug' => $slug,
            ]);

            if ($SteampayApp && $SteampayApp->isNotFound()) {
                $output->writeln('⏩ <comment>Ранее отмечено как 404 (не найдено). Пропускаем.</comment>');
                ++$skippedNotFound;
                continue;
            }

            usleep(random_int(1000000, 2000000));

            try {
                $output->writeln("🌐 <info>Запрашиваем URL: $url</info>");
                $response = $this->httpClient->request('GET', $url);
                $content = $response->getContent(false);

                if (!$SteampayApp) {
                    $SteampayApp = new SteampayApp();
                    $SteampayApp->setSlug($slug);
                    $output->writeln('🆕 <info>Создана новая запись SteampayApp для slug.</info>');
                } else {
                    $output->writeln('🔄 <info>Найдена существующая запись SteampayApp, обновляем.</info>');
                }

                $SteampayApp->setCreatedAt(new \DateTimeImmutable());

                if (
                    str_contains($content, 'Ошибка! Страница не найдена')
                    || preg_match('/<h1[^>]*not-found-error__title[^>]*>Ошибка! Страница не найдена\./', $content)
                ) {
                    $SteampayApp->setNotFound(true);
                    $SteampayApp->setRawHtml(null);
                    $output->writeln('❌ <comment>Страница вернула 404. Отмечаем как не найдено.</comment>');
                } else {
                    $SteampayApp->setNotFound(false);
                    $SteampayApp->setRawHtml(null);

                    $gameShop = new GameShop();
                    $gameShop->setGame($game);
                    $gameShop->setShop($shop);
                    $gameShop->setName((string) $game->getName());
                    $gameShop->setLink($url);
                    $gameShop->setShouldImportPrice(true);
                    $gameShop->setExternalKey($slug);
                    $gameShop->setLinkGameId(0);

                    $this->entityManager->persist($gameShop);

                    $output->writeln("✅ <info>GameShop создан и связан с игрой '{$game->getName()}'.</info>");
                    ++$imported;
                }

                $this->entityManager->persist($SteampayApp);
                $this->entityManager->flush();
            } catch (\Throwable $e) {
                ++$errorsCount;
                $output->writeln("<error>⛔ Ошибка при запросе $slug: {$e->getMessage()}</error>");
            }
        }

        $output->writeln('');
        $output->writeln('📊 <info>Итоги импорта:</info>');
        $output->writeln(' - Всего игр обработано: ' . count($games));
        $output->writeln(" - Связано/импортировано: $imported");
        $output->writeln(" - Пропущено (уже связано): $skippedExistingShop");
        $output->writeln(" - Пропущено (404 ранее): $skippedNotFound");
        $output->writeln(" - Ошибок: $errorsCount");

        return Command::SUCCESS;
    }
}
