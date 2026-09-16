<?php

namespace App\Command\Import;

use App\Cron\Attribute\AsTrackedCron;
use App\Service\Ggsel\GameImportService;
use App\Service\Ggsel\GgselCheckResult;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Проверяет наличие игр на ggsel.net порциями, по убыванию популярности,
 * и сохраняет найденные ссылки. Вся логика — в GameImportService, команда
 * только читает опции, вызывает сервис и печатает результат.
 */
#[AsCommand(
    name: 'app:games:import-ggsel',
    description: 'Проверяет наличие игр на ggsel.net порциями, сохраняет найденные ссылки',
)]
#[AsTrackedCron]
class ImportGgselGamesCommand extends Command
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
            ->addOption('delay-ms', null, InputOption::VALUE_REQUIRED, 'Пауза между запросами к ggsel.net, мс', 1500);
    }

    /** Запускает проверку очередной пачки игр и печатает итог. */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limit = (int) $input->getOption('limit');
        $delayMs = (int) $input->getOption('delay-ms');

        $io->writeln(sprintf(
            'Запуск проверки ggsel.net в %s. Параметры: limit=%d, delay-ms=%d.',
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            $limit,
            $delayMs,
        ));

        $result = $this->gameImportService->importNextBatch($limit, $delayMs);

        if ($result->checkedCount() === 0) {
            $io->warning('Нечего проверять: у всех игр уже есть запись GgselGame.');

            return Command::SUCCESS;
        }

        foreach ($result->results as $checkResult) {
            $this->printCheckResult($io, $checkResult);
        }

        $io->success(sprintf(
            'Готово. Проверено: %d, найдено на ggsel: %d.%s',
            $result->checkedCount(),
            $result->foundCount(),
            $result->wrapped ? ' Курсор дошёл до конца списка и был начат заново в рамках этого же запуска.' : '',
        ));

        return Command::SUCCESS;
    }

    /**
     * Печатает игру и под ней — по строке на каждый проверенный слаг-кандидат
     * (какой URL запрашивали и что там оказалось), чтобы по логу крона было
     * видно не только "не найдено", но и куда именно ходили и почему.
     */
    private function printCheckResult(SymfonyStyle $io, GgselCheckResult $checkResult): void
    {
        $ggselGame = $checkResult->ggselGame;

        $io->writeln($ggselGame === null
            ? sprintf('<comment>— %s</comment> — не найдено', $checkResult->game->getName())
            : sprintf('<info>✓ %s</info> — найдено: %s', $checkResult->game->getName(), $ggselGame->getUrl()));

        foreach ($checkResult->attempts as $attempt) {
            $io->writeln(sprintf('    %s → %s', $attempt->url, $attempt->reason));
        }
    }
}
