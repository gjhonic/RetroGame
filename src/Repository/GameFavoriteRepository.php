<?php

namespace App\Repository;

use App\Entity\Game;
use App\Entity\GameFavorite;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameFavorite>
 */
class GameFavoriteRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности GameFavorite. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameFavorite::class);
    }

    /** Возвращает запись избранного пользователя для игры, если она есть. */
    public function findOneByGameAndUser(Game $game, User $user): ?GameFavorite
    {
        return $this->findOneBy(['game' => $game, 'user' => $user]);
    }

    /**
     * Одна страница избранных игр пользователя (новые сначала), с fetch-join игры (без N+1).
     *
     * @return array<int, GameFavorite>
     */
    public function findForUser(User $user, int $limit, int $offset): array
    {
        return $this->createQueryBuilder('f')
            ->addSelect('g')
            ->join('f.game', 'g')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /** Количество избранных игр пользователя (для расчёта страниц). */
    public function countForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
