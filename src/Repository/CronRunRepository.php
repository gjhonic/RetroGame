<?php

namespace App\Repository;

use App\Entity\CronRun;
use App\Entity\Enum\CronRunStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CronRun>
 */
class CronRunRepository extends ServiceEntityRepository
{
    /** Колонки, по которым разрешена сортировка списка в админке. */
    public const array SORTABLE_FIELDS = ['startedAt', 'command', 'durationMs', 'memoryPeakBytes', 'status'];

    /** Регистрирует репозиторий для сущности CronRun. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CronRun::class);
    }

    /**
     * Страница списка запусков для админки.
     *
     * @param array<string, string> $filters
     *
     * @return array<int, CronRun>
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
        $sortField = \in_array($sortField, self::SORTABLE_FIELDS, true) ? $sortField : 'startedAt';

        $qb = $this->createQueryBuilder('r');
        $this->applyFilters($qb, $filters, $from, $to);
        $qb->addOrderBy('r.' . $sortField, $sortDirection)
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        return $qb->getQuery()->getResult();
    }

    /**
     * Количество запусков, подходящих под фильтры (для расчёта страниц).
     *
     * @param array<string, string> $filters
     */
    public function countForAdminList(array $filters, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to): int
    {
        $qb = $this->createQueryBuilder('r')->select('COUNT(r.id)');
        $this->applyFilters($qb, $filters, $from, $to);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Запуски за диапазон дат для графика-таймлайна — без пагинации, но
     * с разумным потолком на случай очень частых кронов.
     *
     * @return array<int, CronRun>
     */
    public function findForTimeline(?\DateTimeImmutable $from, ?\DateTimeImmutable $to, int $limit = 500): array
    {
        $qb = $this->createQueryBuilder('r');

        if ($from !== null) {
            $qb->andWhere('r.startedAt >= :from')->setParameter('from', $from);
        }

        if ($to !== null) {
            $qb->andWhere('r.startedAt <= :to')->setParameter('to', $to);
        }

        return $qb->orderBy('r.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Имена всех команд, для которых хотя бы раз была запись — источник
     * значений для выпадающего фильтра в админке.
     *
     * @return array<int, string>
     */
    public function findDistinctCommands(): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('DISTINCT r.command')
            ->orderBy('r.command', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($rows, 'command');
    }

    /** Последний по времени старта запуск команды — для колонки в списке кронов. */
    public function findLatest(string $command): ?CronRun
    {
        return $this->findOneBy(['command' => $command], ['startedAt' => 'DESC']);
    }

    /**
     * Запуски, застрявшие в статусе "running" дольше собственного тайм-аута —
     * процесс, скорее всего, был убит и не успел записать своё завершение.
     *
     * @return array<int, CronRun>
     */
    public function findStaleRunning(\DateTimeImmutable $before): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.status = :status')
            ->andWhere('r.startedAt < :before')
            ->setParameter('status', CronRunStatus::Running)
            ->setParameter('before', $before)
            ->getQuery()
            ->getResult();
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
        if (($filters['command'] ?? '') !== '') {
            $qb->andWhere('r.command = :filterCommand')->setParameter('filterCommand', $filters['command']);
        }

        if (($filters['status'] ?? '') !== '') {
            $qb->andWhere('r.status = :filterStatus')->setParameter('filterStatus', $filters['status']);
        }

        if ($from !== null) {
            $qb->andWhere('r.startedAt >= :dateFrom')->setParameter('dateFrom', $from);
        }

        if ($to !== null) {
            $qb->andWhere('r.startedAt <= :dateTo')->setParameter('dateTo', $to);
        }
    }
}
