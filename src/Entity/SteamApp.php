<?php

namespace App\Entity;

use App\Repository\SteamAppRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Сущность приложения Steam.
 *
 * Представляет приложение (игру, DLC и т.д.) из Steam.
 *
 * @ORM\Entity(repositoryClass=SteamAppRepository::class)
 *
 * @ORM\Table(name="steam_apps")
 */
#[ORM\Entity(repositoryClass: SteamAppRepository::class)]
#[ORM\Table(name: 'steam_apps')]
class SteamApp
{
    /**
     * Уникальный идентификатор
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue
     *
     * @ORM\Column
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * ID приложения в Steam.
     *
     * @ORM\Column
     */
    #[ORM\Column]
    private ?int $app_id = null;

    /**
     * Название приложения.
     *
     * @ORM\Column(length=255, nullable=true)
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    /**
     * Тип приложения (например, "game", "dlc").
     *
     * @ORM\Column(length=50)
     */
    #[ORM\Column(length: 50)]
    private ?string $type = null;

    /**
     * Сырые данные, полученные от Steam API.
     *
     * @ORM\Column(type="text", nullable=true)
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rawData = null;

    /**
     * Дата и время создания записи.
     *
     * @ORM\Column
     */
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * Конструктор сущности.
     *
     * Инициализирует дату создания
     */
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * Получить ID.
     *
     * @return int|null Уникальный идентификатор
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Получить ID приложения Steam.
     *
     * @return int|null ID приложения в Steam
     */
    public function getAppId(): ?int
    {
        return $this->app_id;
    }

    /**
     * Установить ID приложения Steam.
     *
     * @param int $app_id ID приложения в Steam
     */
    public function setAppId(int $app_id): static
    {
        $this->app_id = $app_id;

        return $this;
    }

    /**
     * Получить название приложения.
     *
     * @return string|null Название приложения
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Установить название приложения.
     *
     * @param string|null $name Название приложения
     */
    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Получить тип приложения.
     *
     * @return string|null Тип приложения
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Установить тип приложения.
     *
     * @param string $type Тип приложения
     */
    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Получить сырые данные.
     *
     * @return string|null Сырые данные в формате JSON
     */
    public function getRawData(): ?string
    {
        return $this->rawData;
    }

    /**
     * Установить сырые данные.
     *
     * @param string|null $rawData Сырые данные в формате JSON
     */
    public function setRawData(?string $rawData): static
    {
        $this->rawData = $rawData;

        return $this;
    }

    /**
     * Получить дату создания.
     *
     * @return \DateTimeImmutable|null Дата и время создания
     */
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Установить дату создания.
     *
     * @param \DateTimeImmutable $createdAt Дата и время создания
     */
    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
