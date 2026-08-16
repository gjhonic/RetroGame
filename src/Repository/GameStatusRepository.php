<?php

namespace App\Repository;

use App\Entity\Enum\GamePlaythroughStatus;
use App\Entity\Game;
use App\Entity\GameStatus;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameStatus>
 */
class GameStatusRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности GameStatus. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameStatus::class);
    }

    /** Возвращает статус прохождения игры пользователем, если он задан. */
    public function findOneByGameAndUser(Game $game, User $user): ?GameStatus
    {
        return $this->findOneBy(['game' => $game, 'user' => $user]);
    }

    /**
     * Одна страница игр пользователя с заданным статусом (недавно обновлённые сначала),
     * с fetch-join игры (без N+1).
     *
     * @return array<int, GameStatus>
     */
    public function findForUser(User $user, GamePlaythroughStatus $status, int $limit, int $offset): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('g')
            ->join('s.game', 'g')
            ->andWhere('s.user = :user')
            ->andWhere('s.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', $status)
            ->orderBy('s.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /** Количество игр пользователя с заданным статусом (для расчёта страниц). */
    public function countForUser(User $user, GamePlaythroughStatus $status): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.user = :user')
            ->andWhere('s.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
