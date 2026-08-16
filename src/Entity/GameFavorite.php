<?php

namespace App\Entity;

use App\Repository\GameFavoriteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameFavoriteRepository::class)]
#[ORM\Table(name: 'game_favorite')]
#[ORM\UniqueConstraint(name: 'UNIQ_GAME_FAVORITE_GAME_USER', columns: ['game_id', 'user_id'])]
class GameFavorite
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

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** Добавляет игру в избранное пользователя — одна запись на пару (игра, пользователь). */
    public function __construct(Game $game, User $user)
    {
        $this->game = $game;
        $this->user = $user;
        $this->createdAt = new \DateTimeImmutable();
    }

    /** Возвращает ID записи. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Возвращает избранную игру. */
    public function getGame(): Game
    {
        return $this->game;
    }

    /** Возвращает пользователя, добавившего игру в избранное. */
    public function getUser(): User
    {
        return $this->user;
    }

    /** Возвращает дату добавления в избранное. */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
