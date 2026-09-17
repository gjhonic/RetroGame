<?php

namespace App\Command\Import;

use App\Cron\Attribute\AsTrackedCron;
use App\Entity\PlatiGamePrice;
use App\Service\Plati\PriceImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Импорт цен игр на plati.market порциями, раз в день на игру, только для
 * уже найденных ранее игр (app:games:import-plati). Вся логика — в
 * PriceImportService, команда только читает опции, вызывает сервис и
 * печатает результат.
 */
#[AsCommand(
    name: 'app:games:import-plati-prices',
    description: 'Импорт цен игр на plati.market порциями, с сохранением истории по дням',
)]
#[AsTrackedCron]
class ImportPlatiGamePricesCommand extends Command
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
                'delay-ms',
                null,
                InputOption::VALUE_REQUIRED,
                'Пауза между запросами к api.digiseller.ru, мс',
                500,
            );
    }

    /** Запускает импорт очередной пачки и печатает итог. */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limit = (int) $input->getOption('limit');
        $delayMs = (int) $input->getOption('delay-ms');

        $io->writeln(sprintf(
            'Запуск импорта цен plati.market в %s. Параметры: limit=%d, delay-ms=%d.',
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            $limit,
            $delayMs,
        ));

        $result = $this->priceImportService->importNextBatch($limit, $delayMs);

        if ($result->prices === [] && $result->skippedCount === 0) {
            $io->warning('Нечего импортировать: в очереди нет ни одной найденной на plati.market игры.');

            return Command::SUCCESS;
        }

        foreach ($result->prices as $price) {
            $this->printPrice($io, $price);
        }

        $io->success(sprintf(
            'Готово. С ценой: %d, не найдено повторно: %d, пропущено (сетевая ошибка): %d. %s'
            . 'Курсор сохранён на plati_game.id=%d (popularity=%s).',
            $result->countFound(),
            $result->countNotFound(),
            $result->skippedCount,
            $result->startedNewDay ? 'Новый день — курсор сброшен, начат новый круг с самых популярных игр. ' : '',
            $result->lastPlatiGameId,
            $result->lastPopularity ?? '—',
        ));

        return Command::SUCCESS;
    }

    /** Печатает одну обработанную запись цены. */
    private function printPrice(SymfonyStyle $io, PlatiGamePrice $price): void
    {
        $game = $price->getGame();

        if (!$price->isFound()) {
            $io->writeln(sprintf('<comment>%s</comment> — не найдено повторным поиском', $game->getName()));

            return;
        }

        $io->writeln(sprintf(
            '<info>%s</info> — %d RUB',
            $game->getName(),
            $price->getPriceKopecks(),
        ));
    }
}
