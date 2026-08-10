<?php

namespace App\Command\Import;

use App\Cron\Attribute\AsTrackedCron;
use App\Entity\Enum\SteamGameStatus;
use App\Entity\SteamGame;
use App\Service\Steam\GameImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Импорт игр из Steam Web API. Вся логика — в GameImportService,
 * команда только читает опции, вызывает сервис и печатает результат.
 */
#[AsCommand(
    name: 'app:games:import',
    description: 'Импорт игр из Steam порциями, с сохранением в БД',
)]
#[AsTrackedCron]
class ImportGamesCommand extends Command
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
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Сколько игр разбирать за запуск', 20)
            ->addOption(
                'last-appid',
                null,
                InputOption::VALUE_REQUIRED,
                'Курсор: appid, с которого продолжить (по умолчанию — автоматически, с прошлого запуска)',
            )
            ->addOption('delay-ms', null, InputOption::VALUE_REQUIRED, 'Пауза между запросами к Steam, мс', 1500);
    }

    /** Запускает обычный импорт или повтор неудачных попыток и печатает итог. */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limit = (int) $input->getOption('limit');
        $delayMs = (int) $input->getOption('delay-ms');
        $lastAppIdOption = $input->getOption('last-appid');
        $lastAppId = $lastAppIdOption === null ? null : (int) $lastAppIdOption;

        $io->writeln(sprintf(
            'Запуск импорта в %s. Параметры: limit=%d, last-appid=%s, delay-ms=%d.',
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            $limit,
            $lastAppId === null ? 'авто (с прошлого запуска)' : (string) $lastAppId,
            $delayMs,
        ));

        $result = $this->gameImportService->importNextBatch($limit, $lastAppId, $delayMs);

        if ($result->steamGames === []) {
            $io->warning('Нечего импортировать: пустая порция от Steam или нет игр со статусом failed.');

            return Command::SUCCESS;
        }

        foreach ($result->steamGames as $steamGame) {
            $this->printSteamGame($io, $steamGame);
        }

        $io->success(sprintf(
            'Готово. Успешно: %d, с ошибкой: %d. Есть ещё игры: %s. '
            . 'Курсор сохранён на appid=%d — следующий запуск продолжит отсюда автоматически.',
            $result->countByStatus(SteamGameStatus::Success),
            $result->countByStatus(SteamGameStatus::Failed),
            $result->hasMore ? 'да' : 'нет',
            $result->lastAppId,
        ));

        return Command::SUCCESS;
    }

    /** Печатает одну запись: подробности при успехе (игра/DLC), ошибку — при неудаче. */
    private function printSteamGame(SymfonyStyle $io, SteamGame $steamGame): void
    {
        if ($steamGame->getStatus() !== SteamGameStatus::Success) {
            $io->writeln(sprintf(
                '<comment>— appid %d: %s</comment>',
                $steamGame->getSteamAppId(),
                $steamGame->getLastError() ?? 'не удалось получить данные',
            ));

            return;
        }

        $game = $steamGame->getGame();
        if ($game !== null) {
            $io->writeln(sprintf('<info>%s</info> (Steam appid: %d)', $game->getName(), $steamGame->getSteamAppId()));
            $io->listing([
                sprintf('Slug: %s', $game->getSlug()),
                sprintf('Metacritic: %s', $game->getMetacriticScore() ?? '—'),
                sprintf('Обложка: %s', $game->getCoverImagePath() ?? 'не скачана'),
                sprintf('Описание: %s', $this->truncate($game->getDescription())),
            ]);

            return;
        }

        $dlc = $steamGame->getDlc();
        if ($dlc !== null) {
            $io->writeln(sprintf(
                '<info>%s</info> — DLC (Steam appid: %d)',
                $dlc->getName(),
                $steamGame->getSteamAppId(),
            ));
            $io->listing([
                sprintf('Slug: %s', $dlc->getSlug()),
                sprintf('Базовая игра: %s', $dlc->getGame()?->getName() ?? 'ещё не импортирована'),
            ]);

            return;
        }

        $io->writeln(sprintf(
            '<comment>— appid %d: не игра и не DLC, пропущено</comment>',
            $steamGame->getSteamAppId(),
        ));
    }

    /** Обрезает текст до заданной длины, добавляя многоточие. */
    private function truncate(?string $text, int $length = 200): string
    {
        if ($text === null || $text === '') {
            return '—';
        }

        return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '…' : $text;
    }
}
