<?php

namespace App\Entity;

use App\Repository\ShopRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Сущность магазина игр
 * 
 * Представляет игровой магазин, где можно приобрести игры.
 * Связан с играми через промежуточную таблицу GameShop.
 * 
 * @ORM\Entity(repositoryClass=ShopRepository::class)
 * @ORM\Table(name="shops")
 */
#[ORM\Entity(repositoryClass: ShopRepository::class)]
#[ORM\Table(name: 'shops')]
class Shop
{
    /**
     * Уникальный идентификатор магазина
     * 
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Название магазина
     * 
     * @ORM\Column(length=255)
     */
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * Описание магазина
     * 
     * @ORM\Column(type=Types::TEXT)
     */
    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    /**
     * URL магазина
     * 
     * @ORM\Column(length=255)
     */
    #[ORM\Column(length: 255)]
    private ?string $url = null;

    /**
     * Дата и время создания записи магазина
     * 
     * Автоматически устанавливается при создании нового магазина
     * 
     * @ORM\Column(type=Types::DATETIME_MUTABLE)
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    /**
     * Дата и время последнего обновления записи магазина
     * 
     * Автоматически обновляется при каждом изменении данных магазина
     * 
     * @ORM\Column(type=Types::DATETIME_MUTABLE)
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * Коллекция связей магазина с играми
     * 
     * @var Collection<int, GameShop>
     * @ORM\OneToMany(mappedBy="shop", targetEntity=GameShop::class, cascade={"persist"}, orphanRemoval=true)
     */
    #[ORM\OneToMany(mappedBy: 'shop', targetEntity: GameShop::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $gameShops;

    /**
     * Конструктор сущности
     * 
     * Инициализирует коллекцию связей с играми и поля дат создания и обновления
     */
    public function __construct()
    {
        $this->gameShops = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    /**
     * Получить ID магазина
     * 
     * @return int|null Уникальный идентификатор магазина
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Получить название магазина
     * 
     * @return string|null Название магазина
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Установить название магазина
     * 
     * @param string $name Название магазина
     * @return static
     */
    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Получить описание магазина
     * 
     * @return string|null Описание магазина
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Установить описание магазина
     * 
     * @param string $description Описание магазина
     * @return static
     */
    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Получить URL магазина
     * 
     * @return string|null URL магазина
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Установить URL магазина
     * 
     * @param string $url URL магазина
     * @return static
     */
    public function setUrl(string $url): static
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Получить коллекцию связей с играми
     * 
     * @return Collection<int, GameShop> Коллекция связей магазина с играми
     */
    public function getGameShops(): Collection
    {
        return $this->gameShops;
    }

    /**
     * Добавить связь с игрой
     * 
     * @param GameShop $gameShop Связь магазина с игрой
     * @return static
     */
    public function addGameShop(GameShop $gameShop): static
    {
        if (!$this->gameShops->contains($gameShop)) {
            $this->gameShops->add($gameShop);
            $gameShop->setShop($this);
        }

        return $this;
    }

    /**
     * Удалить связь с игрой
     * 
     * @param GameShop $gameShop Связь магазина с игрой
     * @return static
     */
    public function removeGameShop(GameShop $gameShop): static
    {
        if ($this->gameShops->removeElement($gameShop)) {
            if ($gameShop->getShop() === $this) {
                $gameShop->setShop(null);
            }
        }

        return $this;
    }

    /**
     * Получить дату создания записи магазина
     * 
     * @return \DateTimeInterface|null Дата и время создания
     */
    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Установить дату создания записи магазина
     * 
     * @param \DateTimeInterface $createdAt Дата и время создания
     * @return static
     */
    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * Получить дату последнего обновления записи магазина
     * 
     * @return \DateTimeInterface|null Дата и время последнего обновления
     */
    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * Установить дату последнего обновления записи магазина
     * 
     * @param \DateTimeInterface $updatedAt Дата и время обновления
     * @return static
     */
    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * Автоматически обновить дату изменения записи
     * 
     * Вызывается Doctrine перед каждым обновлением записи
     * 
     * @ORM\PreUpdate
     */
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
