<?php

namespace App\Entity;

use App\Repository\GameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Сущность игры
 * 
 * Представляет игру в системе с информацией о названии, описании, дате релиза,
 * жанрах, магазинах где продается и других характеристиках.
 * 
 * @ORM\Entity(repositoryClass=GameRepository::class)
 * @ORM\Table(name="games")
 */
#[ORM\Entity(repositoryClass: GameRepository::class)]
#[ORM\Table(name: 'games')]
class Game
{
    /**
     * Уникальный идентификатор игры
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
     * Название игры
     * 
     * @ORM\Column(length=255)
     */
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * Описание игры
     * 
     * @ORM\Column(type=Types::TEXT)
     */
    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    /**
     * Дата релиза игры
     * 
     * @ORM\Column(type=Types::DATE_MUTABLE)
     */
    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $releaseDate = null;

    /**
     * Коллекция жанров игры
     * 
     * @var Collection<int, Genre>
     * @ORM\ManyToMany(targetEntity=Genre::class, inversedBy="games")
     */
    #[ORM\ManyToMany(targetEntity: Genre::class, inversedBy: 'games')]
    private Collection $genre;

    /**
     * Дата и время создания записи игры
     * 
     * Автоматически устанавливается при создании новой игры
     * 
     * @ORM\Column(type=Types::DATETIME_MUTABLE)
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    /**
     * Дата и время последнего обновления записи игры
     * 
     * Автоматически обновляется при каждом изменении данных игры
     * 
     * @ORM\Column(type=Types::DATETIME_MUTABLE)
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * Популярность игры в Steam (количество владельцев)
     * 
     * @ORM\Column(type="integer", nullable=true)
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $steamPopularity = null;

    /**
     * Коллекция связей игры с магазинами
     * 
     * @var Collection<int, GameShop>
     * @ORM\OneToMany(mappedBy="game", targetEntity=GameShop::class)
     */
    #[ORM\OneToMany(mappedBy: 'game', targetEntity: GameShop::class)]
    private Collection $shops;

    /**
     * Флаг бесплатности игры
     * 
     * @ORM\Column(type="boolean")
     */
    #[ORM\Column(type: 'boolean')]
    private bool $isFree = false;

    /**
     * Путь к изображению игры
     * 
     * @ORM\Column(length=255, nullable=true)
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    /**
     * Конструктор сущности
     * 
     * Инициализирует коллекции жанров и магазинов, а также поля дат создания и обновления
     */
    public function __construct()
    {
        $this->genre = new ArrayCollection();
        $this->shops = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    /**
     * Получить ID игры
     * 
     * @return int|null Уникальный идентификатор игры
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Получить название игры
     * 
     * @return string|null Название игры
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Установить название игры
     * 
     * @param string $name Название игры
     * @return static
     */
    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Получить описание игры
     * 
     * @return string|null Описание игры
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Установить описание игры
     * 
     * @param string $description Описание игры
     * @return static
     */
    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Получить дату релиза игры
     * 
     * @return \DateTimeInterface|null Дата релиза
     */
    public function getReleaseDate(): ?\DateTimeInterface
    {
        return $this->releaseDate;
    }

    /**
     * Установить дату релиза игры
     * 
     * @param \DateTimeInterface $releaseDate Дата релиза
     * @return static
     */
    public function setReleaseDate(\DateTimeInterface $releaseDate): static
    {
        $this->releaseDate = $releaseDate;
        return $this;
    }

    /**
     * Получить коллекцию жанров игры
     * 
     * @return Collection<int, Genre> Коллекция жанров
     */
    public function getGenre(): Collection
    {
        return $this->genre;
    }

    /**
     * Добавить жанр к игре
     * 
     * @param Genre $genre Жанр для добавления
     * @return static
     */
    public function addGenre(Genre $genre): static
    {
        if (!$this->genre->contains($genre)) {
            $this->genre->add($genre);
        }
        return $this;
    }

    /**
     * Удалить жанр из игры
     * 
     * @param Genre $genre Жанр для удаления
     * @return static
     */
    public function removeGenre(Genre $genre): static
    {
        $this->genre->removeElement($genre);
        return $this;
    }

    /**
     * Получить дату создания записи игры
     * 
     * @return \DateTimeInterface|null Дата и время создания
     */
    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Установить дату создания записи игры
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
     * Получить дату последнего обновления записи игры
     * 
     * @return \DateTimeInterface|null Дата и время последнего обновления
     */
    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * Установить дату последнего обновления записи игры
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
     * Получить популярность игры в Steam
     * 
     * @return int|null Количество владельцев в Steam
     */
    public function getSteamPopularity(): ?int
    {
        return $this->steamPopularity;
    }

    /**
     * Установить популярность игры в Steam
     * 
     * @param int|null $steamPopularity Количество владельцев в Steam
     * @return static
     */
    public function setSteamPopularity(?int $steamPopularity): static
    {
        $this->steamPopularity = $steamPopularity;
        return $this;
    }

    /**
     * Получить коллекцию связей с магазинами
     * 
     * @return Collection<int, GameShop> Коллекция связей с магазинами
     */
    public function getShops(): Collection
    {
        return $this->shops;
    }

    /**
     * Добавить связь с магазином
     * 
     * @param GameShop $shop Связь с магазином
     * @return static
     */
    public function addShop(GameShop $shop): static
    {
        if (!$this->shops->contains($shop)) {
            $this->shops->add($shop);
            $shop->setGame($this);
        }
        return $this;
    }

    /**
     * Удалить связь с магазином
     * 
     * @param GameShop $shop Связь с магазином
     * @return static
     */
    public function removeShop(GameShop $shop): static
    {
        if ($this->shops->removeElement($shop)) {
            if ($shop->getGame() === $this) {
                $shop->setGame(null);
            }
        }
        return $this;
    }

    /**
     * Проверить, является ли игра бесплатной
     * 
     * @return bool True если игра бесплатная
     */
    public function isFree(): bool
    {
        return $this->isFree;
    }

    /**
     * Установить флаг бесплатности игры
     * 
     * @param bool $isFree Флаг бесплатности
     * @return static
     */
    public function setIsFree(bool $isFree): static
    {
        $this->isFree = $isFree;
        return $this;
    }

    /**
     * Получить путь к изображению игры
     * 
     * @return string|null Путь к изображению
     */
    public function getImage(): ?string
    {
        return $this->image;
    }

    /**
     * Установить путь к изображению игры
     * 
     * @param string|null $image Путь к изображению
     * @return static
     */
    public function setImage(?string $image): static
    {
        $this->image = $image;
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
