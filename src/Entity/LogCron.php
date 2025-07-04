<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Сущность для логирования выполнения крон-команд.
 *
 * Позволяет сохранять информацию о запуске, времени выполнения,
 * максимальном объёме памяти и других параметрах работы кронов.
 * Используется для аудита и мониторинга фоновых задач.
 *
 * @ORM\Entity
 * @ORM\Table(name="logs_cron")
 */
#[ORM\Entity]
#[ORM\Table(name: 'logs_cron')]
class LogCron
{
    /**
     * Уникальный идентификатор записи лога.
     *
     * @var int|null
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Имя крона (например: steambuy-update-prices)
     *
     * @var string
     */
    #[ORM\Column(length: 100)]
    private string $cronName;

    /**
     * Дата и время старта выполнения крон-команды.
     *
     * @var \DateTimeInterface|null
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $datetimeStart = null;

    /**
     * Дата и время окончания выполнения крон-команды.
     * Может быть null, если задача ещё выполняется или завершилась с ошибкой.
     *
     * @var \DateTimeInterface|null
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $datetimeEnd = null;

    /**
     * Максимальный объём памяти, использованный в процессе выполнения (МБ).
     * Может быть null, если не удалось определить.
     *
     * @var float|null
     */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $maxMemorySize = null;

    /**
     * Время работы крон-команды (секунды, float).
     * Может быть null, если задача не завершилась корректно.
     *
     * @var float|null
     */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $workTime = null;

    /**
     * Дата создания записи лога.
     * Устанавливается автоматически при создании объекта.
     *
     * @var \DateTimeInterface|null
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    /**
     * Конструктор. Устанавливает дату создания записи.
     */
    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    /**
     * Получить ID записи лога.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Получить имя крон-команды.
     *
     * @return string
     */
    public function getCronName(): string
    {
        return $this->cronName;
    }

    /**
     * Установить имя крон-команды.
     *
     * @param string $cronName
     * @return static
     */
    public function setCronName(string $cronName): static
    {
        $this->cronName = $cronName;
        return $this;
    }

    /**
     * Получить дату и время старта выполнения.
     *
     * @return \DateTimeInterface|null
     */
    public function getDatetimeStart(): ?\DateTimeInterface
    {
        return $this->datetimeStart;
    }

    /**
     * Установить дату и время старта выполнения.
     *
     * @param \DateTimeInterface $datetimeStart
     * @return static
     */
    public function setDatetimeStart(\DateTimeInterface $datetimeStart): static
    {
        $this->datetimeStart = $datetimeStart;
        return $this;
    }

    /**
     * Получить дату и время окончания выполнения.
     *
     * @return \DateTimeInterface|null
     */
    public function getDatetimeEnd(): ?\DateTimeInterface
    {
        return $this->datetimeEnd;
    }

    /**
     * Установить дату и время окончания выполнения.
     *
     * @param \DateTimeInterface|null $datetimeEnd
     * @return static
     */
    public function setDatetimeEnd(?\DateTimeInterface $datetimeEnd): static
    {
        $this->datetimeEnd = $datetimeEnd;
        return $this;
    }

    /**
     * Получить максимальный объём памяти (МБ).
     *
     * @return float|null
     */
    public function getMaxMemorySize(): ?float
    {
        return $this->maxMemorySize;
    }

    /**
     * Установить максимальный объём памяти (МБ).
     *
     * @param float|null $maxMemorySize
     * @return static
     */
    public function setMaxMemorySize(?float $maxMemorySize): static
    {
        $this->maxMemorySize = $maxMemorySize;
        return $this;
    }

    /**
     * Получить время работы (секунды).
     *
     * @return float|null
     */
    public function getWorkTime(): ?float
    {
        return $this->workTime;
    }

    /**
     * Установить время работы (секунды).
     *
     * @param float|null $workTime
     * @return static
     */
    public function setWorkTime(?float $workTime): static
    {
        $this->workTime = $workTime;
        return $this;
    }

    /**
     * Получить дату создания записи.
     *
     * @return \DateTimeInterface|null
     */
    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Установить дату создания записи.
     *
     * @param \DateTimeInterface $createdAt
     * @return static
     */
    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
