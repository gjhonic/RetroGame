<?php

namespace App\Repository;

use App\Entity\Enum\GameReactionType;
use App\Entity\Game;
use App\Entity\GameReaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameReaction>
 */
class GameReactionRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности GameReaction. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameReaction::class);
    }

    /** Возвращает голос пользователя за игру, если он есть — не более одного (unique constraint). */
    public function findOneByGameAndUser(Game $game, User $user): ?GameReaction
    {
        return $this->findOneBy(['game' => $game, 'user' => $user]);
    }

    /**
     * Счётчики лайков/дизлайков одной игры.
     *
     * @return array{like: int, dislike: int}
     */
    public function countByTypeForGame(Game $game): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.type AS type', 'COUNT(r.id) AS cnt')
            ->andWhere('r.game = :game')
            ->setParameter('game', $game)
            ->groupBy('r.type')
            ->getQuery()
            ->getResult();

        return $this->mergeCounts($rows);
    }

    /**
     * @param array<int, array{type: GameReactionType, cnt: int}> $rows
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
}
