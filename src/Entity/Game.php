<?php

namespace App\Entity;

use App\Repository\GameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameRepository::class)]
class Game
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255, unique: true)]
    private string $slug;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $releaseDate = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $coverImagePath = null;

    /** @var Collection<int, Developer> */
    #[ORM\ManyToMany(targetEntity: Developer::class)]
    #[ORM\JoinTable(name: 'game_developer')]
    private Collection $developers;

    /** @var Collection<int, Publisher> */
    #[ORM\ManyToMany(targetEntity: Publisher::class)]
    #[ORM\JoinTable(name: 'game_publisher')]
    private Collection $publishers;

    /** @var Collection<int, Genre> */
    #[ORM\ManyToMany(targetEntity: Genre::class)]
    #[ORM\JoinTable(name: 'game_genre')]
    private Collection $genres;

    /** @var Collection<int, Platform> */
    #[ORM\ManyToMany(targetEntity: Platform::class)]
    #[ORM\JoinTable(name: 'game_platform')]
    private Collection $platforms;

    /** @var array<int, string>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $screenshotUrls = null;

    #[ORM\Column(nullable: true)]
    private ?float $rating = null;

    #[ORM\Column(nullable: true)]
    private ?int $metacriticScore = null;

    /** Число обзоров/рекомендаций в Steam (recommendations.total) — прокси-показатель популярности игры. */
    #[ORM\Column(nullable: true)]
    private ?int $popularity = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** Создаёт игру с обязательными полями, остальное — через сеттеры. */
    public function __construct(string $name, string $slug)
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->developers = new ArrayCollection();
        $this->publishers = new ArrayCollection();
        $this->genres = new ArrayCollection();
        $this->platforms = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** Возвращает ID игры. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Возвращает название игры. */
    public function getName(): string
    {
        return $this->name;
    }

    /** Задаёт название игры. */
    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /** Возвращает slug (используется в URL). */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /** Задаёт slug. */
    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    /** Возвращает описание игры. */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /** Задаёт описание игры. */
    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /** Возвращает дату выхода игры. */
    public function getReleaseDate(): ?\DateTimeImmutable
    {
        return $this->releaseDate;
    }

    /** Задаёт дату выхода игры. */
    public function setReleaseDate(?\DateTimeImmutable $releaseDate): static
    {
        $this->releaseDate = $releaseDate;

        return $this;
    }

    /** Возвращает путь к локально скачанной обложке (относительно public/). */
    public function getCoverImagePath(): ?string
    {
        return $this->coverImagePath;
    }

    /** Задаёт путь к локально скачанной обложке. */
    public function setCoverImagePath(?string $coverImagePath): static
    {
        $this->coverImagePath = $coverImagePath;

        return $this;
    }

    /**
     * Возвращает разработчиков игры.
     *
     * @return Collection<int, Developer>
     */
    public function getDevelopers(): Collection
    {
        return $this->developers;
    }

    /** Добавляет разработчика, если его ещё нет в списке. */
    public function addDeveloper(Developer $developer): static
    {
        if (!$this->developers->contains($developer)) {
            $this->developers->add($developer);
        }

        return $this;
    }

    /**
     * Возвращает издателей игры.
     *
     * @return Collection<int, Publisher>
     */
    public function getPublishers(): Collection
    {
        return $this->publishers;
    }

    /** Добавляет издателя, если его ещё нет в списке. */
    public function addPublisher(Publisher $publisher): static
    {
        if (!$this->publishers->contains($publisher)) {
            $this->publishers->add($publisher);
        }

        return $this;
    }

    /**
     * Возвращает жанры игры.
     *
     * @return Collection<int, Genre>
     */
    public function getGenres(): Collection
    {
        return $this->genres;
    }

    /** Добавляет жанр, если его ещё нет в списке. */
    public function addGenre(Genre $genre): static
    {
        if (!$this->genres->contains($genre)) {
            $this->genres->add($genre);
        }

        return $this;
    }

    /**
     * Возвращает платформы игры (Windows, macOS, Linux).
     *
     * @return Collection<int, Platform>
     */
    public function getPlatforms(): Collection
    {
        return $this->platforms;
    }

    /** Добавляет платформу, если её ещё нет в списке. */
    public function addPlatform(Platform $platform): static
    {
        if (!$this->platforms->contains($platform)) {
            $this->platforms->add($platform);
        }

        return $this;
    }

    /**
     * Возвращает ссылки на скриншоты.
     *
     * @return array<int, string>|null
     */
    public function getScreenshotUrls(): ?array
    {
        return $this->screenshotUrls;
    }

    /**
     * Задаёт ссылки на скриншоты.
     *
     * @param array<int, string>|null $screenshotUrls
     */
    public function setScreenshotUrls(?array $screenshotUrls): static
    {
        $this->screenshotUrls = $screenshotUrls;

        return $this;
    }

    /** Возвращает рейтинг игры. */
    public function getRating(): ?float
    {
        return $this->rating;
    }

    /** Задаёт рейтинг игры. */
    public function setRating(?float $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    /** Возвращает оценку Metacritic. */
    public function getMetacriticScore(): ?int
    {
        return $this->metacriticScore;
    }

    /** Задаёт оценку Metacritic. */
    public function setMetacriticScore(?int $metacriticScore): static
    {
        $this->metacriticScore = $metacriticScore;

        return $this;
    }

    /** Возвращает популярность игры (число обзоров/рекомендаций в Steam). */
    public function getPopularity(): ?int
    {
        return $this->popularity;
    }

    /** Задаёт популярность игры. */
    public function setPopularity(?int $popularity): static
    {
        $this->popularity = $popularity;

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

    /** Обновляет дату последнего изменения на текущий момент. */
    public function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
