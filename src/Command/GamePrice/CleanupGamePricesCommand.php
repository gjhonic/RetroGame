<?php

namespace App\Command\GamePrice;

use App\Cron\Attribute\AsTrackedCron;
use App\Service\GamePrice\GamePriceCleanupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Удаление промежуточных снимков цены игр за последние N недель — вся
 * логика в GamePriceCleanupService, команда только читает опции, вызывает
 * сервис и печатает результат.
 */
#[AsCommand(
    name: 'app:games:cleanup-prices',
    description: 'Удаление промежуточных снимков цены игр — храним только цену до и после изменения',
)]
#[AsTrackedCron]
class CleanupGamePricesCommand extends Command
{
    public function __construct(private readonly GamePriceCleanupService $cleanupService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'weeks',
            null,
            InputOption::VALUE_REQUIRED,
            'За сколько недель назад от сегодня обрабатывать снимки цен',
            2,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $weeks = (int) $input->getOption('weeks');

        $io->writeln(sprintf(
            'Запуск очистки цен в %s. Параметры: weeks=%d.',
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            $weeks,
        ));

        $result = $this->cleanupService->cleanup($weeks);

        $io->success(sprintf(
            'Готово. Обработано игр: %d, удалено промежуточных записей цены: %d.',
            $result->processedGamesCount,
            $result->deletedCount,
        ));

        return Command::SUCCESS;
    }
}
