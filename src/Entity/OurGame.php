<?php

namespace App\Entity;

use App\Entity\Enum\OurGameStatus;
use App\Repository\OurGameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/** Игра собственной разработки, которую мы продвигаем на сайте (в отличие от Game — каталога RAWG/Steam). */
#[ORM\Entity(repositoryClass: OurGameRepository::class)]
class OurGame
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

    #[ORM\Column(length: 20, enumType: OurGameStatus::class)]
    private OurGameStatus $status;

    /** Текущая версия игры, например "1.2.0". */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $currentVersion = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $releaseDate = null;

    /** Когда обновлялась currentVersion в последний раз. */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $versionUpdatedAt = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $trailerUrl = null;

    /** Основная обложка (относительно public/). */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $coverImagePath = null;

    /** Широкий баннер/хиро-изображение для карточки игры (относительно public/). */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $bannerImagePath = null;

    /** @var array<int, string>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $screenshotUrls = null;

    /** @var Collection<int, Genre> */
    #[ORM\ManyToMany(targetEntity: Genre::class)]
    #[ORM\JoinTable(name: 'our_game_genre')]
    private Collection $genres;

    /** @var Collection<int, OurGameDownloadLink> */
    #[ORM\OneToMany(
        targetEntity: OurGameDownloadLink::class,
        mappedBy: 'ourGame',
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    private Collection $downloadLinks;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** Создаёт игру с обязательными полями, остальное — через сеттеры. */
    public function __construct(string $name, string $slug, OurGameStatus $status = OurGameStatus::Draft)
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->status = $status;
        $this->genres = new ArrayCollection();
        $this->downloadLinks = new ArrayCollection();
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

    /** Возвращает slug (используется в будущем публичном URL /our-games/{slug}). */
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

    /** Возвращает статус публикации. */
    public function getStatus(): OurGameStatus
    {
        return $this->status;
    }

    /** Задаёт статус публикации. */
    public function setStatus(OurGameStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    /** Возвращает текущую версию игры. */
    public function getCurrentVersion(): ?string
    {
        return $this->currentVersion;
    }

    /** Задаёт текущую версию игры и фиксирует момент обновления. */
    public function setCurrentVersion(?string $currentVersion): static
    {
        $this->currentVersion = $currentVersion;
        $this->versionUpdatedAt = new \DateTimeImmutable();

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

    /** Возвращает дату последнего обновления текущей версии. */
    public function getVersionUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->versionUpdatedAt;
    }

    /** Возвращает ссылку на трейлер. */
    public function getTrailerUrl(): ?string
    {
        return $this->trailerUrl;
    }

    /** Задаёт ссылку на трейлер. */
    public function setTrailerUrl(?string $trailerUrl): static
    {
        $this->trailerUrl = $trailerUrl;

        return $this;
    }

    /** Возвращает путь к основной обложке (относительно public/). */
    public function getCoverImagePath(): ?string
    {
        return $this->coverImagePath;
    }

    /** Задаёт путь к основной обложке. */
    public function setCoverImagePath(?string $coverImagePath): static
    {
        $this->coverImagePath = $coverImagePath;

        return $this;
    }

    /** Возвращает путь к баннеру (относительно public/). */
    public function getBannerImagePath(): ?string
    {
        return $this->bannerImagePath;
    }

    /** Задаёт путь к баннеру. */
    public function setBannerImagePath(?string $bannerImagePath): static
    {
        $this->bannerImagePath = $bannerImagePath;

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

    /** Убирает жанр из списка. */
    public function removeGenre(Genre $genre): static
    {
        $this->genres->removeElement($genre);

        return $this;
    }

    /**
     * Возвращает ссылки на скачивание игры.
     *
     * @return Collection<int, OurGameDownloadLink>
     */
    public function getDownloadLinks(): Collection
    {
        return $this->downloadLinks;
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
