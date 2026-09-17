<?php

namespace App\Command\Import;

use App\Cron\Attribute\AsTrackedCron;
use App\Service\Plati\GameImportService;
use App\Service\Plati\PlatiCheckResult;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Проверяет наличие игр на plati.market порциями, по убыванию популярности,
 * и сохраняет найденные ссылки. Вся логика — в GameImportService, команда
 * только читает опции, вызывает сервис и печатает результат.
 */
#[AsCommand(
    name: 'app:games:import-plati',
    description: 'Проверяет наличие игр на plati.market порциями, сохраняет найденные ссылки',
)]
#[AsTrackedCron]
class ImportPlatiGamesCommand extends Command
{
    /** Принимает сервис импорта. */
    public function __construct(private readonly GameImportService $gameImportService)
    {
        parent::__construct();
    }

    /** Описывает опции командной строки. */
    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Сколько игр проверять за запуск', 100)
            ->addOption(
                'delay-ms',
                null,
                InputOption::VALUE_REQUIRED,
                'Пауза между запросами к api.digiseller.ru, мс',
                500,
            );
    }

    /** Запускает проверку очередной пачки игр и печатает итог. */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limit = (int) $input->getOption('limit');
        $delayMs = (int) $input->getOption('delay-ms');

        $io->writeln(sprintf(
            'Запуск проверки plati.market в %s. Параметры: limit=%d, delay-ms=%d.',
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            $limit,
            $delayMs,
        ));

        $result = $this->gameImportService->importNextBatch($limit, $delayMs);

        if ($result->checkedCount() === 0) {
            $io->warning('Нечего проверять: у всех игр уже есть запись PlatiGame.');

            return Command::SUCCESS;
        }

        foreach ($result->results as $checkResult) {
            $this->printCheckResult($io, $checkResult);
        }

        $io->success(sprintf(
            'Готово. Проверено: %d, найдено на plati.market: %d.%s',
            $result->checkedCount(),
            $result->foundCount(),
            $result->wrapped ? ' Курсор дошёл до конца списка и был начат заново в рамках этого же запуска.' : '',
        ));

        return Command::SUCCESS;
    }

    /** Печатает игру, найдена или нет, ссылки продавцов (если найдены) и причину — для подробного лога крона. */
    private function printCheckResult(SymfonyStyle $io, PlatiCheckResult $checkResult): void
    {
        if (!$checkResult->isFound()) {
            $io->writeln(sprintf(
                '<comment>— %s</comment> — не найдено (%s)',
                $checkResult->game->getName(),
                $checkResult->reason,
            ));

            return;
        }

        $io->writeln(sprintf('<info>✓ %s</info> (%s):', $checkResult->game->getName(), $checkResult->reason));
        foreach ($checkResult->platiGames as $platiGame) {
            $io->writeln(sprintf('    — %s: %s', $platiGame->getSellerName(), $platiGame->getUrl()));
        }
    }
}
