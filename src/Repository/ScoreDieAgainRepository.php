<?php

namespace App\Repository;

use App\Entity\ScoreDieAgain;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ScoreDieAgain>
 */
class ScoreDieAgainRepository extends ServiceEntityRepository
{
    /** @var array<int, string> поля, по которым разрешена сортировка (защита от инъекции в DQL) */
    private const array SORTABLE_FIELDS = ['kills', 'survivedSeconds', 'level', 'createdAt'];

    /** Регистрирует репозиторий для сущности ScoreDieAgain. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScoreDieAgain::class);
    }

    /** Общее количество сохранённых результатов. */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Таблица лидеров с сортировкой и постраничной навигацией.
     *
     * @return array<int, ScoreDieAgain>
     */
    public function findForLeaderboard(string $sortBy, string $sortDir, int $limit, int $offset): array
    {
        $field = \in_array($sortBy, self::SORTABLE_FIELDS, true) ? $sortBy : 'kills';

        return $this->createQueryBuilder('s')
            ->orderBy('s.' . $field, $sortDir)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }
}
