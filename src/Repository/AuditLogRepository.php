<?php

namespace App\Repository;

use App\Entity\AuditLog;
use App\Entity\Enum\AuditLogStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    /** Колонки, по которым разрешена сортировка списка в админке. */
    public const array SORTABLE_FIELDS = ['createdAt', 'action', 'status'];

    /** Регистрирует репозиторий для сущности AuditLog. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /**
     * Страница журнала действий для админки: фильтры по действию/статусу/автору
     * и диапазону дат, сортировка и постраничная навигация — всё в БД.
     *
     * @param array<string, string> $filters
     *
     * @return array<int, AuditLog>
     */
    public function findForAdminList(
        array $filters,
        ?\DateTimeImmutable $from,
        ?\DateTimeImmutable $to,
        string $sortField,
        string $sortDirection,
        int $limit,
        int $offset,
    ): array {
        $sortField = \in_array($sortField, self::SORTABLE_FIELDS, true) ? $sortField : 'createdAt';

        $qb = $this->createQueryBuilder('l')->addSelect('u')->leftJoin('l.user', 'u');
        $this->applyFilters($qb, $filters, $from, $to);
        $qb->addOrderBy('l.' . $sortField, $sortDirection)
            ->addOrderBy('l.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        return $qb->getQuery()->getResult();
    }

    /**
     * Количество записей, подходящих под фильтры (для расчёта страниц).
     *
     * @param array<string, string> $filters
     */
    public function countForAdminList(array $filters, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to): int
    {
        $qb = $this->createQueryBuilder('l')->select('COUNT(l.id)');
        $this->applyFilters($qb, $filters, $from, $to);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Уникальные значения action, для которых хотя бы раз была запись — источник
     * значений для выпадающего фильтра в админке.
     *
     * @return array<int, string>
     */
    public function findDistinctActions(): array
    {
        $rows = $this->createQueryBuilder('l')
            ->select('DISTINCT l.action')
            ->orderBy('l.action', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($rows, 'action');
    }

    /**
     * @param array<string, string> $filters
     */
    private function applyFilters(
        QueryBuilder $qb,
        array $filters,
        ?\DateTimeImmutable $from,
        ?\DateTimeImmutable $to,
    ): void {
        if (($filters['action'] ?? '') !== '') {
            $qb->andWhere('l.action = :filterAction')->setParameter('filterAction', $filters['action']);
        }

        if (($filters['status'] ?? '') !== '' && AuditLogStatus::tryFrom($filters['status']) !== null) {
            $qb->andWhere('l.status = :filterStatus')
                ->setParameter('filterStatus', AuditLogStatus::from($filters['status']));
        }

        if (($filters['user'] ?? '') !== '' && ctype_digit($filters['user'])) {
            $qb->andWhere('l.user = :filterUser')->setParameter('filterUser', (int) $filters['user']);
        }

        if ($from !== null) {
            $qb->andWhere('l.createdAt >= :dateFrom')->setParameter('dateFrom', $from);
        }

        if ($to !== null) {
            $qb->andWhere('l.createdAt <= :dateTo')->setParameter('dateTo', $to);
        }
    }
}
