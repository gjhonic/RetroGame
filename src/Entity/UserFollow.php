<?php

namespace App\Entity;

use App\Repository\UserFollowRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserFollowRepository::class)]
#[ORM\Table(name: 'user_follow')]
#[ORM\UniqueConstraint(name: 'UNIQ_USER_FOLLOW_FOLLOWER_FOLLOWED', columns: ['follower_id', 'followed_id'])]
class UserFollow
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'follower_id', nullable: false, onDelete: 'CASCADE')]
    private User $follower;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'followed_id', nullable: false, onDelete: 'CASCADE')]
    private User $followed;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** Подписывает $follower на $followed — одна запись на пару (подписчик, автор). */
    public function __construct(User $follower, User $followed)
    {
        $this->follower = $follower;
        $this->followed = $followed;
        $this->createdAt = new \DateTimeImmutable();
    }

    /** Возвращает ID записи. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Возвращает подписчика. */
    public function getFollower(): User
    {
        return $this->follower;
    }

    /** Возвращает того, на кого подписались. */
    public function getFollowed(): User
    {
        return $this->followed;
    }

    /** Возвращает дату подписки. */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
