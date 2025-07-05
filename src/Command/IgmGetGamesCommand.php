<?php

namespace App\Command;

use App\Entity\Game;
use App\Entity\GameShop;
use App\Entity\IgmApp;
use App\Entity\LogCron;
use App\Entity\Shop;
use App\Service\SlugifyProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Команда импорта игр из IGM.gg.
 *
 * Системное имя: app:igm-get-games
 *
 * Назначение:
 *   Связывает существующие игры в базе данных с магазином IGM.gg, если для них существует валидная страница.
 *
 * Логика работы:
 *   - Получает все игры из базы данных.
 *   - Для каждой игры:
 *       - Генерирует slug на основе названия игры.
 *       - Проверяет существование страницы на IGM.gg по URL.
 *       - Если страница существует — создает связь GameShop.
 *       - Если страница не найдена — отмечает в IgmApp как not found.
 *   - Ограничивает обработку 200 играми за запуск.
 *   - Делает паузы между запросами для избежания блокировки.
 *
 * Логирование:
 *   - Фиксирует начало и конец выполнения.
 *   - Записывает количество связанных игр, пропущенных и ошибок.
 *   - Логирует время работы и пиковое потребление памяти.
 *
 * Использование:
 *   php bin/console app:igm-get-games
 *
 * Особенности:
 *   - Использует SlugifyProcessor для генерации URL-дружественных имен.
 *   - Проверяет существующие связи перед созданием новых.
 *   - Кэширует информацию о не найденных страницах в IgmApp.
 *   - Делает случайные паузы между запросами (1-2 секунды).
 */

#[AsCommand(
    name: 'app:igm-get-games',
    description: 'Links games with IGM.gg if a valid page exists.',
)]
class IgmGetGamesCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startTime = microtime(true);

        $games = $this->entityManager->getRepository(Game::class)->findAll();
        $shop = $this->entityManager->getRepository(Shop::class)->findOneBy(['id' => 5]);

        // --- Логирование старта ---
        $logsCron = new LogCron();
        $logsCron->setCronName('igm-get-games');
        $logsCron->setDatetimeStart(new \DateTime());
        $this->entityManager->persist($logsCron);
        $this->entityManager->flush();

        if (!$shop) {
            $output->writeln('<error>⛔ Магазин с ID 4 (IGM.gg) не найден</error>');

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
            $url = "https://igm.gg/game/{$slug}/";

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

            // Проверка IgmApp
            $igmApp = $this->entityManager->getRepository(IgmApp::class)->findOneBy([
                'slug' => $slug,
            ]);

            if ($igmApp && $igmApp->isNotFound()) {
                $output->writeln('⏩ <comment>Ранее отмечено как 404 (не найдено). Пропускаем.</comment>');
                ++$skippedNotFound;
                continue;
            }

            usleep(random_int(1000000, 2000000));

            try {
                $output->writeln("🌐 <info>Запрашиваем URL: $url</info>");
                $response = $this->httpClient->request('GET', $url);
                $content = $response->getContent(false);

                if (!$igmApp) {
                    $igmApp = new IgmApp();
                    $igmApp->setSlug($slug);
                    $output->writeln('🆕 <info>Создана новая запись IgmApp для slug.</info>');
                } else {
                    $output->writeln('🔄 <info>Найдена существующая запись IgmApp, обновляем.</info>');
                }

                $igmApp->setCreatedAt(new \DateTimeImmutable());

                // Проверяем title страницы для определения наличия игры
                if (preg_match('/<title>(.*?)<\/title>/i', $content, $titleMatches)) {
                    $title = trim($titleMatches[1]);

                    // Проверяем, является ли это страницей 404 (общий title магазина)
                    if (
                        str_contains($title, 'IGM.GG - Магазин видеоигр для ПК')
                        || str_contains($title, 'Купить ключи Steam')
                    ) {
                        $isNotFound = true;
                    } else {
                        // Проверяем, содержит ли title название игры
                        $gameName = (string)$game->getName();
                        if (str_contains($title, $gameName)) {
                            $isNotFound = false;
                        } else {
                            $isNotFound = true;
                        }
                    }
                } else {
                    $isNotFound = true;
                }

                if ($isNotFound) {
                    $igmApp->setNotFound(true);
                    $igmApp->setRawHtml(null);
                    $output->writeln('❌ <comment>Страница вернула 404. Отмечаем как не найдено.</comment>');
                } else {
                    $igmApp->setNotFound(false);
                    $igmApp->setRawHtml(null);

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

                $this->entityManager->persist($igmApp);
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
