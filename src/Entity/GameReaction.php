<?php

namespace App\Entity;

use App\Entity\Enum\GameReactionType;
use App\Repository\GameReactionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameReactionRepository::class)]
#[ORM\Table(name: 'game_reaction')]
#[ORM\UniqueConstraint(name: 'UNIQ_GAME_REACTION_GAME_USER', columns: ['game_id', 'user_id'])]
class GameReaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Game::class)]
    #[ORM\JoinColumn(name: 'game_id', nullable: false, onDelete: 'CASCADE')]
    private Game $game;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 10, enumType: GameReactionType::class)]
    private GameReactionType $type;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** Создаёт реакцию пользователя на игру — один голос на пару (игра, пользователь). */
    public function __construct(Game $game, User $user, GameReactionType $type)
    {
        $this->game = $game;
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

    /** Возвращает игру, к которой относится реакция. */
    public function getGame(): Game
    {
        return $this->game;
    }

    /** Возвращает пользователя, поставившего реакцию. */
    public function getUser(): User
    {
        return $this->user;
    }

    /** Возвращает тип реакции. */
    public function getType(): GameReactionType
    {
        return $this->type;
    }

    /** Меняет тип реакции (лайк ↔ дизлайк). */
    public function setType(GameReactionType $type): static
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
