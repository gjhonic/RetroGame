<?php

namespace App\Entity;

use App\Repository\GenreRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Сущность жанра игры
 *
 * Представляет жанр игры (например: Action, RPG, Strategy и т.д.).
 * Связан с играми через связь ManyToMany.
 *
 * @ORM\Entity(repositoryClass=GenreRepository::class)
 * @ORM\Table(name="genres")
 */
#[ORM\Entity(repositoryClass: GenreRepository::class)]
#[ORM\Table(name: 'genres')]
class Genre
{
    /**
     * Уникальный идентификатор жанра
     *
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Название жанра (уникальное)
     *
     * @ORM\Column(length=255, unique=true)
     */
    #[ORM\Column(length: 255, unique: true)]
    private string $name;

    /**
     * Название жанра на русском языке
     *
     * @ORM\Column(length=255, nullable=true)
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nameRussia = null;

    /**
     * Описание жанра
     *
     * @ORM\Column(type="text", nullable=true)
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * Дата и время создания записи жанра
     *
     * Автоматически устанавливается при создании нового жанра
     *
     * @ORM\Column(type=Types::DATETIME_MUTABLE)
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    /**
     * Дата и время последнего обновления записи жанра
     *
     * Автоматически обновляется при каждом изменении данных жанра
     *
     * @ORM\Column(type=Types::DATETIME_MUTABLE)
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * Коллекция игр данного жанра
     *
     * @var Collection<int, Game>
     * @ORM\ManyToMany(targetEntity=Game::class, mappedBy="genre")
     */
    #[ORM\ManyToMany(targetEntity: Game::class, mappedBy: 'genre')]
    private Collection $games;

    /**
     * Конструктор сущности
     *
     * Инициализирует коллекцию игр и поля дат создания и обновления
     */
    public function __construct()
    {
        $this->games = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    /**
     * Получить ID жанра
     *
     * @return int|null Уникальный идентификатор жанра
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Получить название жанра
     *
     * @return string Название жанра
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Установить название жанра
     *
     * @param string $name Название жанра
     * @return self
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Получить название жанра на русском языке
     *
     * @return string|null Название жанра на русском
     */
    public function getNameRussia(): ?string
    {
        return $this->nameRussia ?? $this->name;
    }

    /**
     * Установить название жанра на русском языке
     *
     * @param string|null $nameRussia Название жанра на русском
     * @return self
     */
    public function setNameRussia(?string $nameRussia): self
    {
        $this->nameRussia = $nameRussia;
        return $this;
    }

    /**
     * Получить описание жанра
     *
     * @return string|null Описание жанра
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Установить описание жанра
     *
     * @param string|null $description Описание жанра
     * @return self
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Строковое представление жанра
     *
     * @return string Название жанра
     */
    public function __toString(): string
    {
        return $this->getName();
    }

    /**
     * Получить коллекцию игр данного жанра
     *
     * @return Collection<int, Game> Коллекция игр
     */
    public function getGames(): Collection
    {
        return $this->games;
    }

    /**
     * Добавить игру к жанру
     *
     * @param Game $game Игра для добавления
     * @return static
     */
    public function addGame(Game $game): static
    {
        if (!$this->games->contains($game)) {
            $this->games->add($game);
            $game->addGenre($this);
        }

        return $this;
    }

    /**
     * Удалить игру из жанра
     *
     * @param Game $game Игра для удаления
     * @return static
     */
    public function removeGame(Game $game): static
    {
        if ($this->games->removeElement($game)) {
            $game->removeGenre($this);
        }

        return $this;
    }

    /**
     * Получить дату создания записи жанра
     *
     * @return \DateTimeInterface|null Дата и время создания
     */
    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Установить дату создания записи жанра
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
     * Получить дату последнего обновления записи жанра
     *
     * @return \DateTimeInterface|null Дата и время последнего обновления
     */
    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * Установить дату последнего обновления записи жанра
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
