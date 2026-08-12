<?php

namespace App\Entity;

use App\Entity\Enum\OurGamePostType;
use App\Entity\Enum\OurGameStatus;
use App\Repository\OurGamePostRepository;
use Doctrine\ORM\Mapping as ORM;

/** Пост об игре собственной разработки: анонс, обычное или крупное обновление. */
#[ORM\Entity(repositoryClass: OurGamePostRepository::class)]
class OurGamePost
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: OurGame::class)]
    #[ORM\JoinColumn(name: 'game_id', nullable: false, onDelete: 'CASCADE')]
    private OurGame $game;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'author_id', nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[ORM\Column(length: 20, enumType: OurGamePostType::class)]
    private OurGamePostType $type;

    #[ORM\Column(length: 20, enumType: OurGameStatus::class)]
    private OurGameStatus $status;

    /** Дата, которой датирован пост (не путать с createdAt — техническим временем создания записи). */
    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $postedAt;

    #[ORM\Column(length: 255)]
    private string $title;

    /** Вертикальная картинка поста (относительно public/). */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imagePath = null;

    /** HTML из редактора в админке (Admin/RichTextEditor.vue) — без ограничения длины. */
    #[ORM\Column(type: 'text')]
    private string $shortDescription;

    /** HTML из редактора в админке — без ограничения длины. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $fullDescription = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** Создаёт пост с обязательными полями, остальное — через сеттеры. */
    public function __construct(
        OurGame $game,
        User $author,
        OurGamePostType $type,
        \DateTimeImmutable $postedAt,
        string $title,
        string $shortDescription,
        OurGameStatus $status = OurGameStatus::Draft,
    ) {
        $this->game = $game;
        $this->author = $author;
        $this->type = $type;
        $this->postedAt = $postedAt;
        $this->title = $title;
        $this->shortDescription = $shortDescription;
        $this->status = $status;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** Возвращает ID поста. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Возвращает игру, к которой относится пост. */
    public function getGame(): OurGame
    {
        return $this->game;
    }

    /** Задаёт игру, к которой относится пост. */
    public function setGame(OurGame $game): static
    {
        $this->game = $game;

        return $this;
    }

    /** Возвращает автора поста. */
    public function getAuthor(): User
    {
        return $this->author;
    }

    /** Возвращает тип поста. */
    public function getType(): OurGamePostType
    {
        return $this->type;
    }

    /** Задаёт тип поста. */
    public function setType(OurGamePostType $type): static
    {
        $this->type = $type;

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

    /** Возвращает дату поста. */
    public function getPostedAt(): \DateTimeImmutable
    {
        return $this->postedAt;
    }

    /** Задаёт дату поста. */
    public function setPostedAt(\DateTimeImmutable $postedAt): static
    {
        $this->postedAt = $postedAt;

        return $this;
    }

    /** Возвращает заголовок поста. */
    public function getTitle(): string
    {
        return $this->title;
    }

    /** Задаёт заголовок поста. */
    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /** Возвращает путь к картинке поста (относительно public/). */
    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    /** Задаёт путь к картинке поста. */
    public function setImagePath(?string $imagePath): static
    {
        $this->imagePath = $imagePath;

        return $this;
    }

    /** Возвращает краткое описание. */
    public function getShortDescription(): string
    {
        return $this->shortDescription;
    }

    /** Задаёт краткое описание. */
    public function setShortDescription(string $shortDescription): static
    {
        $this->shortDescription = $shortDescription;

        return $this;
    }

    /** Возвращает полное описание. */
    public function getFullDescription(): ?string
    {
        return $this->fullDescription;
    }

    /** Задаёт полное описание. */
    public function setFullDescription(?string $fullDescription): static
    {
        $this->fullDescription = $fullDescription;

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
