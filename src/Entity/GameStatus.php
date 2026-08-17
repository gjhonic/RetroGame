<?php

namespace App\Entity;

use App\Entity\Enum\GamePlaythroughStatus;
use App\Repository\GameStatusRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameStatusRepository::class)]
#[ORM\Table(name: 'game_status')]
#[ORM\UniqueConstraint(name: 'UNIQ_GAME_STATUS_GAME_USER', columns: ['game_id', 'user_id'])]
class GameStatus
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

    #[ORM\Column(length: 15, enumType: GamePlaythroughStatus::class)]
    private GamePlaythroughStatus $status;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** Задаёт статус прохождения игры пользователем — один статус на пару (игра, пользователь). */
    public function __construct(Game $game, User $user, GamePlaythroughStatus $status)
    {
        $this->game = $game;
        $this->user = $user;
        $this->status = $status;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** Возвращает ID записи. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Возвращает игру, к которой относится статус. */
    public function getGame(): Game
    {
        return $this->game;
    }

    /** Возвращает пользователя. */
    public function getUser(): User
    {
        return $this->user;
    }

    /** Возвращает статус прохождения. */
    public function getStatus(): GamePlaythroughStatus
    {
        return $this->status;
    }

    /** Меняет статус прохождения. */
    public function setStatus(GamePlaythroughStatus $status): static
    {
        $this->status = $status;

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
