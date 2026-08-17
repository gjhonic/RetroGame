<?php

namespace App\Repository;

use App\Entity\Enum\UserReportType;
use App\Entity\UserReport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserReport>
 */
class UserReportRepository extends ServiceEntityRepository
{
    /** Колонки, по которым разрешена сортировка списка в админке. */
    public const array SORTABLE_FIELDS = ['createdAt', 'type'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserReport::class);
    }

    /**
     * Страница отчётов для админки: фильтр по типу, сортировка, постраничная навигация.
     *
     * @param array<string, string> $filters
     *
     * @return array<int, UserReport>
     */
    public function findForAdminList(
        array $filters,
        string $sortField,
        string $sortDirection,
        int $limit,
        int $offset,
    ): array {
        $sortField = \in_array($sortField, self::SORTABLE_FIELDS, true) ? $sortField : 'createdAt';

        $qb = $this->createQueryBuilder('r');
        $this->applyFilters($qb, $filters);
        $qb->addOrderBy('r.' . $sortField, $sortDirection)
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array<string, string> $filters
     */
    public function countForAdminList(array $filters): int
    {
        $qb = $this->createQueryBuilder('r')->select('COUNT(r.id)');
        $this->applyFilters($qb, $filters);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param array<string, string> $filters
     */
    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        if (($filters['type'] ?? '') !== '' && ctype_digit((string) $filters['type'])) {
            $type = UserReportType::tryFrom((int) $filters['type']);
            if ($type !== null) {
                $qb->andWhere('r.type = :filterType')->setParameter('filterType', $type);
            }
        }
    }
}
