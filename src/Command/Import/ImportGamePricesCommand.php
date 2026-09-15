<?php

namespace App\Command\Import;

use App\Cron\Attribute\AsTrackedCron;
use App\Entity\GamePrice;
use App\Service\Steam\PriceImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Импорт цен игр из Steam (регион RU) порциями, раз в день на игру.
 * Вся логика — в PriceImportService, команда только читает опции,
 * вызывает сервис и печатает результат.
 */
#[AsCommand(
    name: 'app:games:import-prices',
    description: 'Импорт цен игр из Steam (регион RU) порциями, с сохранением истории по дням',
)]
#[AsTrackedCron]
class ImportGamePricesCommand extends Command
{
    /** Принимает сервис импорта цен. */
    public function __construct(private readonly PriceImportService $priceImportService)
    {
        parent::__construct();
    }

    /** Описывает опции командной строки. */
    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Сколько игр обрабатывать за запуск', 100)
            ->addOption(
                'min-delay-ms',
                null,
                InputOption::VALUE_REQUIRED,
                'Минимальная пауза между запросами к Steam, мс',
                1000,
            )
            ->addOption(
                'max-delay-ms',
                null,
                InputOption::VALUE_REQUIRED,
                'Максимальная пауза между запросами к Steam, мс',
                3000,
            );
    }

    /** Запускает импорт очередной пачки и печатает итог. */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limit = (int) $input->getOption('limit');
        $minDelayMs = (int) $input->getOption('min-delay-ms');
        $maxDelayMs = (int) $input->getOption('max-delay-ms');

        if ($minDelayMs > $maxDelayMs) {
            $io->error(sprintf(
                'min-delay-ms (%d) не может быть больше max-delay-ms (%d).',
                $minDelayMs,
                $maxDelayMs,
            ));

            return Command::FAILURE;
        }

        $io->writeln(sprintf(
            'Запуск импорта цен в %s. Параметры: limit=%d, delay-ms=%d..%d.',
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            $limit,
            $minDelayMs,
            $maxDelayMs,
        ));

        $result = $this->priceImportService->importNextBatch($limit, $minDelayMs, $maxDelayMs);

        if ($result->prices === [] && $result->skippedCount === 0) {
            $io->warning('Нечего импортировать: в каталоге нет ни одной успешно импортированной игры.');

            return Command::SUCCESS;
        }

        foreach ($result->prices as $price) {
            $this->printPrice($io, $price);
        }

        $io->success(sprintf(
            'Готово. С ценой: %d, бесплатных: %d, недоступно в РФ: %d, пропущено (сетевая ошибка): %d. %s'
            . 'Курсор сохранён на steam_game.id=%d (popularity=%s).',
            $result->countPriced(),
            $result->countFree(),
            $result->countUnavailable(),
            $result->skippedCount,
            $result->wrapped ? 'Дошли до конца каталога, начат новый круг. ' : '',
            $result->lastSteamGameId,
            $result->lastPopularity ?? '—',
        ));

        return Command::SUCCESS;
    }

    /** Печатает одну обработанную запись цены. */
    private function printPrice(SymfonyStyle $io, GamePrice $price): void
    {
        $game = $price->getGame();

        if ($price->isFree()) {
            $io->writeln(sprintf('<info>%s</info> — бесплатно', $game->getName()));

            return;
        }

        if (!$price->isAvailableInRussia()) {
            $io->writeln(sprintf('<comment>%s</comment> — недоступна в РФ', $game->getName()));

            return;
        }

        $io->writeln(sprintf(
            '<info>%s</info> — %d RUB',
            $game->getName(),
            $price->getPriceKopecks(),
        ));
    }
}
