<?php

namespace App\Entity;

use App\Repository\TakeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TakeRepository::class)]
class Take
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'author_id', nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[ORM\ManyToOne(targetEntity: Game::class)]
    #[ORM\JoinColumn(name: 'game_id', nullable: false, onDelete: 'CASCADE')]
    private Game $game;

    #[ORM\Column(length: 1000)]
    private string $text;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** Создаёт тэйк — неизменяем после создания (MVP без редактирования). */
    public function __construct(User $author, Game $game, string $text)
    {
        $this->author = $author;
        $this->game = $game;
        $this->text = $text;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** Возвращает ID тэйка. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Возвращает автора тэйка. */
    public function getAuthor(): User
    {
        return $this->author;
    }

    /** Возвращает игру, к которой относится тэйк. */
    public function getGame(): Game
    {
        return $this->game;
    }

    /** Возвращает текст тэйка. */
    public function getText(): string
    {
        return $this->text;
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
