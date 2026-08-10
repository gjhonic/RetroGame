<?php

namespace App\Repository;

use App\Entity\Enum\TakeReactionType;
use App\Entity\Take;
use App\Entity\TakeReaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TakeReaction>
 */
class TakeReactionRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности TakeReaction. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TakeReaction::class);
    }

    /** Возвращает голос пользователя за тэйк, если он есть — не более одного (unique constraint). */
    public function findOneByTakeAndUser(Take $take, User $user): ?TakeReaction
    {
        return $this->findOneBy(['take' => $take, 'user' => $user]);
    }

    /**
     * Счётчики лайков/дизлайков одного тэйка.
     *
     * @return array{like: int, dislike: int}
     */
    public function countByTypeForTake(Take $take): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.type AS type', 'COUNT(r.id) AS cnt')
            ->andWhere('r.take = :take')
            ->setParameter('take', $take)
            ->groupBy('r.type')
            ->getQuery()
            ->getResult();

        return $this->mergeCounts($rows);
    }

    /**
     * Счётчики лайков/дизлайков для списка тэйков одним запросом (без N+1).
     *
     * @param array<int, int> $takeIds
     *
     * @return array<int, array{like: int, dislike: int}>
     */
    public function countByTypeForTakes(array $takeIds): array
    {
        if ($takeIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.take) AS takeId', 'r.type AS type', 'COUNT(r.id) AS cnt')
            ->andWhere('r.take IN (:takeIds)')
            ->setParameter('takeIds', $takeIds)
            ->groupBy('takeId', 'r.type')
            ->getQuery()
            ->getResult();

        return $this->mergeCountsByTake($takeIds, $rows);
    }

    /**
     * @param array<int, array{type: TakeReactionType, cnt: int}> $rows
     *
     * @return array{like: int, dislike: int}
     */
    private function mergeCounts(array $rows): array
    {
        $counts = ['like' => 0, 'dislike' => 0];
        foreach ($rows as $row) {
            $counts[$row['type']->value] = (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * @param array<int, int> $takeIds
     * @param array<int, array{takeId: int, type: TakeReactionType, cnt: int}> $rows
     *
     * @return array<int, array{like: int, dislike: int}>
     */
    private function mergeCountsByTake(array $takeIds, array $rows): array
    {
        $likeCounts = [];
        $dislikeCounts = [];
        foreach ($rows as $row) {
            $takeId = (int) $row['takeId'];
            $cnt = (int) $row['cnt'];
            match ($row['type']) {
                TakeReactionType::Like => $likeCounts[$takeId] = $cnt,
                TakeReactionType::Dislike => $dislikeCounts[$takeId] = $cnt,
            };
        }

        $counts = [];
        foreach ($takeIds as $takeId) {
            $counts[$takeId] = [
                'like' => $likeCounts[$takeId] ?? 0,
                'dislike' => $dislikeCounts[$takeId] ?? 0,
            ];
        }

        return $counts;
    }
}
