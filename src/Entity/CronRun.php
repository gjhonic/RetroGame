<?php

namespace App\Entity;

use App\Entity\Enum\CronRunStatus;
use App\Repository\CronRunRepository;
use Doctrine\ORM\Mapping as ORM;

/** Один запуск консольной команды, помеченной #[AsTrackedCron] — см. CronTrackingListener. */
#[ORM\Entity(repositoryClass: CronRunRepository::class)]
#[ORM\Index(columns: ['command'], name: 'IDX_CRON_RUN_COMMAND')]
#[ORM\Index(columns: ['started_at'], name: 'IDX_CRON_RUN_STARTED_AT')]
#[ORM\Index(columns: ['status'], name: 'IDX_CRON_RUN_STATUS')]
class CronRun
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Имя команды (Command::getName()), например "app:games:import". */
    #[ORM\Column(length: 255)]
    private string $command;

    /** Аргументы/опции запуска в виде строки — как были переданы в консоль. */
    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $arguments = null;

    #[ORM\Column(length: 20, enumType: CronRunStatus::class)]
    private CronRunStatus $status;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $durationMs = null;

    #[ORM\Column(nullable: true)]
    private ?int $memoryPeakBytes = null;

    #[ORM\Column(nullable: true)]
    private ?int $exitCode = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    /** Путь к файлу с полным выводом команды, относительно var/log/cron/. */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $logPath = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** Создаёт запись о начавшемся запуске команды. */
    public function __construct(string $command, ?string $arguments, ?string $logPath)
    {
        $this->command = $command;
        $this->arguments = $arguments;
        $this->logPath = $logPath;
        $this->status = CronRunStatus::Running;
        $this->startedAt = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** Возвращает ID запуска. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Возвращает имя команды. */
    public function getCommand(): string
    {
        return $this->command;
    }

    /** Возвращает аргументы запуска в виде строки. */
    public function getArguments(): ?string
    {
        return $this->arguments;
    }

    /** Возвращает статус запуска. */
    public function getStatus(): CronRunStatus
    {
        return $this->status;
    }

    /** Возвращает время старта. */
    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    /** Возвращает время завершения (null — ещё выполняется). */
    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    /** Возвращает длительность выполнения в миллисекундах. */
    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    /** Возвращает пиковое потребление памяти в байтах. */
    public function getMemoryPeakBytes(): ?int
    {
        return $this->memoryPeakBytes;
    }

    /** Возвращает код завершения процесса. */
    public function getExitCode(): ?int
    {
        return $this->exitCode;
    }

    /** Возвращает сообщение об ошибке (заполнено при статусе Failed). */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /** Возвращает путь к файлу лога. */
    public function getLogPath(): ?string
    {
        return $this->logPath;
    }

    /** Задаёт путь к файлу лога — выставляется отдельно, когда становится известен ID записи. */
    public function setLogPath(?string $logPath): static
    {
        $this->logPath = $logPath;

        return $this;
    }

    /** Возвращает дату создания записи. */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Возвращает дату последнего обновления записи. */
    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** Фиксирует штатное или аварийное завершение запуска. */
    public function finish(CronRunStatus $status, int $exitCode, int $memoryPeakBytes, ?string $errorMessage): static
    {
        $now = new \DateTimeImmutable();

        $this->status = $status;
        $this->finishedAt = $now;
        $this->durationMs = (int) round(($now->format('U.u') - $this->startedAt->format('U.u')) * 1000);
        $this->exitCode = $exitCode;
        $this->memoryPeakBytes = $memoryPeakBytes;
        $this->errorMessage = $errorMessage;

        return $this->touch();
    }

    /** Обновляет дату последнего изменения на текущий момент. */
    public function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
