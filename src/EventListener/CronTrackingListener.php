<?php

namespace App\EventListener;

use App\Cron\Attribute\AsTrackedCron;
use App\Cron\Console\TeeStreamFilter;
use App\Entity\CronRun;
use App\Entity\Enum\CronRunStatus;
use App\Repository\CronRunRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Ведёт учёт запусков команд, помеченных #[AsTrackedCron]: пишет строку в
 * cron_run на старте и на финише (длительность, память, exit-код, статус),
 * а полный вывод команды дублирует в файл через TeeStreamFilter — без
 * изменения самих команд.
 *
 * Один процесс консоли выполняет одну команду за раз, поэтому состояние
 * текущего запуска (currentRun и хендлы) хранится прямо в полях сервиса —
 * это безопасно, так как сервис не переиспользуется параллельно.
 */
#[AsEventListener(event: ConsoleEvents::COMMAND, method: 'onCommand')]
#[AsEventListener(event: ConsoleEvents::ERROR, method: 'onError')]
#[AsEventListener(event: ConsoleEvents::TERMINATE, method: 'onTerminate')]
class CronTrackingListener
{
    private ?CronRun $currentRun = null;
    private ?string $currentErrorMessage = null;

    /** @var list<resource> */
    private array $streamFilters = [];

    /** @var resource|null */
    private $logFileHandle = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CronRunRepository $cronRunRepository,
        private readonly Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/var/log/cron')]
        private readonly string $logDir,
    ) {
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        $attribute = $command === null ? [] : (new \ReflectionClass($command))->getAttributes(AsTrackedCron::class);

        if ($attribute === []) {
            return;
        }

        /** @var AsTrackedCron $tracked */
        $tracked = $attribute[0]->newInstance();
        $this->healStaleRuns($tracked->staleAfterMinutes);

        $commandName = $command->getName() ?? $command::class;

        $this->currentRun = new CronRun($commandName, (string) $event->getInput(), null);
        $this->entityManager->persist($this->currentRun);
        $this->entityManager->flush();

        $logPath = $this->buildLogPath($commandName, (int) $this->currentRun->getId());
        $this->currentRun->setLogPath($logPath);
        $this->entityManager->flush();

        $this->attachLogFile($event->getOutput(), $this->logDir . '/' . $logPath);
    }

    public function onError(ConsoleErrorEvent $event): void
    {
        $this->currentErrorMessage = $event->getError()->getMessage();
    }

    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        if ($this->currentRun === null) {
            return;
        }

        $status = $event->getExitCode() === 0 ? CronRunStatus::Success : CronRunStatus::Failed;
        $this->currentRun->finish(
            $status,
            $event->getExitCode(),
            memory_get_peak_usage(true),
            $this->currentErrorMessage,
        );

        try {
            $this->entityManager->flush();
        } catch (\Throwable) {
            // EntityManager мог быть уже закрыт исключением из самой команды (например,
            // DBAL-ошибкой при flush в её собственном коде) — падать здесь не нужно:
            // это заслонило бы в логе крона настоящую причину сбоя своим стектрейсом.
        }

        $this->detachLogFile();
        $this->currentRun = null;
        $this->currentErrorMessage = null;
    }

    /**
     * Помечает failed запуски, застрявшие в "running" дольше тайм-аута —
     * упрощение для прототипа: используется тайм-аут стартующей сейчас
     * команды, а не индивидуальный тайм-аут каждого зависшего запуска.
     */
    private function healStaleRuns(int $staleAfterMinutes): void
    {
        $before = (new \DateTimeImmutable())->modify(sprintf('-%d minutes', $staleAfterMinutes));

        $staleRuns = $this->cronRunRepository->findStaleRunning($before);

        if ($staleRuns === []) {
            return;
        }

        foreach ($staleRuns as $staleRun) {
            $staleRun->finish(
                CronRunStatus::Failed,
                -1,
                0,
                'Запуск не завершился штатно (процесс, вероятно, был прерван) и помечен как зависший.',
            );
        }

        $this->entityManager->flush();
    }

    private function buildLogPath(string $commandName, int $runId): string
    {
        return str_replace(':', '_', $commandName) . '/' . $runId . '.log';
    }

    private function attachLogFile(OutputInterface $output, string $absoluteLogPath): void
    {
        $this->filesystem->mkdir(\dirname($absoluteLogPath));

        $handle = fopen($absoluteLogPath, 'a');
        if ($handle === false) {
            return;
        }

        $this->logFileHandle = $handle;
        TeeStreamFilter::register();

        foreach ($this->collectStreams($output) as $stream) {
            $filter = stream_filter_append($stream, TeeStreamFilter::NAME, STREAM_FILTER_WRITE, ['handle' => $handle]);
            if ($filter !== false) {
                $this->streamFilters[] = $filter;
            }
        }
    }

    private function detachLogFile(): void
    {
        foreach ($this->streamFilters as $filter) {
            stream_filter_remove($filter);
        }
        $this->streamFilters = [];

        if ($this->logFileHandle !== null) {
            fclose($this->logFileHandle);
            $this->logFileHandle = null;
        }
    }

    /**
     * @return list<resource>
     */
    private function collectStreams(OutputInterface $output): array
    {
        $streams = [];

        if ($output instanceof StreamOutput) {
            $streams[] = $output->getStream();
        }

        if ($output instanceof ConsoleOutputInterface) {
            $errorOutput = $output->getErrorOutput();
            if ($errorOutput instanceof StreamOutput) {
                $streams[] = $errorOutput->getStream();
            }
        }

        return $streams;
    }
}
