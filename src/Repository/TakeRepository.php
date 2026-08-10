<?php

namespace App\Repository;

use App\Entity\Take;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Take>
 */
class TakeRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности Take. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Take::class);
    }

    /**
     * Одна страница публичного списка тэйков: опциональный фильтр по игре,
     * сортировка по дате создания.
     *
     * @param array<string, string> $filters
     *
     * @return array<int, Take>
     */
    public function findForPublicList(
        array $filters,
        string $sortField,
        string $sortDirection,
        int $limit,
        int $offset,
    ): array {
        $qb = $this->createQueryBuilder('t');
        $this->applyPublicFilters($qb, $filters);
        $this->applyPublicSort($qb, $sortField, $sortDirection);

        return $qb->addOrderBy('t.id', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Количество тэйков публичного списка, подходящих под фильтры (для расчёта страниц).
     *
     * @param array<string, string> $filters
     */
    public function countForPublicList(array $filters): int
    {
        $qb = $this->createQueryBuilder('t')->select('COUNT(t.id)');
        $this->applyPublicFilters($qb, $filters);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param array<string, string> $filters
     */
    private function applyPublicFilters(QueryBuilder $qb, array $filters): void
    {
        if (($filters['game'] ?? '') !== '' && ctype_digit($filters['game'])) {
            $qb->andWhere('t.game = :filterGame')->setParameter('filterGame', (int) $filters['game']);
        }
    }

    /** MVP: единственное поддерживаемое поле сортировки — createdAt. */
    private function applyPublicSort(QueryBuilder $qb, string $sortField, string $sortDirection): void
    {
        match ($sortField) {
            default => $qb->addOrderBy('t.createdAt', $sortDirection),
        };
    }
}
