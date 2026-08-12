<?php

namespace App\Entity;

use App\Repository\CronRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Справочник кронов, обнаруженных по атрибуту #[AsTrackedCron] на классах
 * Command (см. App\Cron\CronDiscoveryService) — хранит только то, что нельзя
 * получить из кода: настраиваемое пользователем название и цвет для графика.
 */
#[ORM\Entity(repositoryClass: CronRepository::class)]
class Cron
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Имя команды (Command::getName()), например "app:games:import". */
    #[ORM\Column(length: 255, unique: true)]
    private string $command;

    /** Человекочитаемое название, заданное пользователем (по умолчанию показывается command). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    /** Цвет для графика в формате #RRGGBB. */
    #[ORM\Column(length: 7, nullable: true)]
    private ?string $color = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $command)
    {
        $this->command = $command;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this->touch();
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;

        return $this->touch();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
