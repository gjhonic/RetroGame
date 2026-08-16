<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserFollow;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserFollow>
 */
class UserFollowRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности UserFollow. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserFollow::class);
    }

    /** Возвращает подписку $follower на $followed, если она есть. */
    public function findOneByFollowerAndFollowed(User $follower, User $followed): ?UserFollow
    {
        return $this->findOneBy(['follower' => $follower, 'followed' => $followed]);
    }

    /**
     * Одна страница подписчиков пользователя (новые сначала), с fetch-join подписчика (без N+1).
     *
     * @return array<int, UserFollow>
     */
    public function findFollowers(User $user, int $limit, int $offset): array
    {
        return $this->createQueryBuilder('f')
            ->addSelect('u')
            ->join('f.follower', 'u')
            ->andWhere('f.followed = :user')
            ->setParameter('user', $user)
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Одна страница тех, на кого подписан пользователь (новые сначала), с fetch-join (без N+1).
     *
     * @return array<int, UserFollow>
     */
    public function findFollowing(User $user, int $limit, int $offset): array
    {
        return $this->createQueryBuilder('f')
            ->addSelect('u')
            ->join('f.followed', 'u')
            ->andWhere('f.follower = :user')
            ->setParameter('user', $user)
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /** Количество подписчиков пользователя. */
    public function countFollowers(User $user): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.followed = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Количество тех, на кого подписан пользователь. */
    public function countFollowing(User $user): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.follower = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
