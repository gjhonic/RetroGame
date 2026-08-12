<?php

namespace App\Repository;

use App\Entity\Enum\UserRole;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности User. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /** Ищет пользователя по email. */
    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Одна страница пользователей для таблицы в админке: фильтры по колонкам,
     * сортировка и постраничная навигация — всё на стороне БД. Связей у User нет,
     * поэтому, в отличие от GameRepository::findForAdminList(), достаточно одного запроса.
     *
     * @param array<string, string> $filters
     *
     * @return array<int, User>
     */
    public function findForAdminList(
        array $filters,
        string $sortField,
        string $sortDirection,
        int $limit,
        int $offset,
    ): array {
        $qb = $this->createQueryBuilder('u');
        $this->applyAdminFilters($qb, $filters);
        $this->applyAdminSort($qb, $sortField, $sortDirection);

        return $qb->addOrderBy('u.id', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Количество пользователей, подходящих под фильтры (для расчёта страниц).
     *
     * @param array<string, string> $filters
     */
    public function countForAdminList(array $filters): int
    {
        $qb = $this->createQueryBuilder('u')->select('COUNT(u.id)');
        $this->applyAdminFilters($qb, $filters);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function applyAdminSort(QueryBuilder $qb, string $sortField, string $sortDirection): void
    {
        // PostgreSQL по умолчанию кладёт NULL первым при ORDER BY ... DESC —
        // нам же нужно, чтобы пользователи без ника/входов всегда были в конце.
        match ($sortField) {
            'nickname' => $qb
                ->addOrderBy('CASE WHEN u.nickname IS NULL THEN 1 ELSE 0 END', 'ASC')
                ->addOrderBy('u.nickname', $sortDirection),
            'role' => $qb->addOrderBy('u.role', $sortDirection),
            'createdAt' => $qb->addOrderBy('u.createdAt', $sortDirection),
            'lastLoginAt' => $qb
                ->addOrderBy('CASE WHEN u.lastLoginAt IS NULL THEN 1 ELSE 0 END', 'ASC')
                ->addOrderBy('u.lastLoginAt', $sortDirection),
            default => $qb->addOrderBy('u.email', $sortDirection),
        };
    }

    /**
     * @param array<string, string> $filters
     */
    private function applyAdminFilters(QueryBuilder $qb, array $filters): void
    {
        // LOWER() с обеих сторон — LIKE в PostgreSQL по умолчанию регистрозависим.
        if (($filters['email'] ?? '') !== '') {
            $qb->andWhere('LOWER(u.email) LIKE LOWER(:filterEmail)')
                ->setParameter('filterEmail', '%' . $filters['email'] . '%');
        }

        if (($filters['nickname'] ?? '') !== '') {
            $qb->andWhere('LOWER(u.nickname) LIKE LOWER(:filterNickname)')
                ->setParameter('filterNickname', '%' . $filters['nickname'] . '%');
        }

        if (($filters['role'] ?? '') !== '' && UserRole::tryFrom($filters['role']) !== null) {
            $qb->andWhere('u.role = :filterRole')
                ->setParameter('filterRole', UserRole::from($filters['role']));
        }
    }
}
