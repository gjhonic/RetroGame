<?php

namespace App\Entity;

use App\Entity\Enum\TakeReactionType;
use App\Repository\TakeReactionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TakeReactionRepository::class)]
#[ORM\Table(name: 'take_reaction')]
#[ORM\UniqueConstraint(name: 'UNIQ_TAKE_REACTION_TAKE_USER', columns: ['take_id', 'user_id'])]
class TakeReaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Take::class)]
    #[ORM\JoinColumn(name: 'take_id', nullable: false, onDelete: 'CASCADE')]
    private Take $take;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 10, enumType: TakeReactionType::class)]
    private TakeReactionType $type;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** Создаёт реакцию пользователя на тэйк — один голос на пару (тэйк, пользователь). */
    public function __construct(Take $take, User $user, TakeReactionType $type)
    {
        $this->take = $take;
        $this->user = $user;
        $this->type = $type;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** Возвращает ID реакции. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Возвращает тэйк, к которому относится реакция. */
    public function getTake(): Take
    {
        return $this->take;
    }

    /** Возвращает пользователя, поставившего реакцию. */
    public function getUser(): User
    {
        return $this->user;
    }

    /** Возвращает тип реакции. */
    public function getType(): TakeReactionType
    {
        return $this->type;
    }

    /** Меняет тип реакции (лайк ↔ дизлайк). */
    public function setType(TakeReactionType $type): static
    {
        $this->type = $type;

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
