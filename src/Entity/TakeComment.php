<?php

namespace App\Entity;

use App\Repository\TakeCommentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TakeCommentRepository::class)]
#[ORM\Table(name: 'take_comment')]
class TakeComment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Take::class)]
    #[ORM\JoinColumn(name: 'take_id', nullable: false, onDelete: 'CASCADE')]
    private Take $take;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'author_id', nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[ORM\Column(length: 1000)]
    private string $text;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** Создаёт комментарий к тэйку — неизменяем после создания (MVP без редактирования). */
    public function __construct(Take $take, User $author, string $text)
    {
        $this->take = $take;
        $this->author = $author;
        $this->text = $text;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** Возвращает ID комментария. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Возвращает тэйк, к которому относится комментарий. */
    public function getTake(): Take
    {
        return $this->take;
    }

    /** Возвращает автора комментария. */
    public function getAuthor(): User
    {
        return $this->author;
    }

    /** Возвращает текст комментария. */
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
