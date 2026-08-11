<?php

namespace App\Repository;

use App\Entity\Enum\SteamGameStatus;
use App\Entity\SteamGame;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SteamGame>
 */
class SteamGameRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности SteamGame. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SteamGame::class);
    }

    /** Ищет запись по appid игры в Steam. */
    public function findOneBySteamAppId(int $steamAppId): ?SteamGame
    {
        return $this->findOneBy(['steamAppId' => $steamAppId]);
    }

    /**
     * Одна страница записей Steam-игр для таблицы в админке: фильтры по
     * колонкам, сортировка и постраничная навигация — всё на стороне БД.
     * В отличие от GameRepository::findForAdminList() двухшаговый запрос по
     * id не нужен: связь с Game — OneToOne, join не даёт дублей строк.
     *
     * @param array<string, string> $filters
     *
     * @return array<int, SteamGame>
     */
    public function findForAdminList(
        array $filters,
        string $sortField,
        string $sortDirection,
        int $limit,
        int $offset,
    ): array {
        $qb = $this->baseAdminQueryBuilder()->addSelect('game');
        $this->applyAdminFilters($qb, $filters);
        $this->applyAdminSort($qb, $sortField, $sortDirection);

        return $qb->addOrderBy('s.id', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Количество записей, подходящих под фильтры (для расчёта страниц).
     *
     * @param array<string, string> $filters
     */
    public function countForAdminList(array $filters): int
    {
        $qb = $this->baseAdminQueryBuilder()->select('COUNT(s.id)');
        $this->applyAdminFilters($qb, $filters);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function baseAdminQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('s')->leftJoin('s.game', 'game');
    }

    private function applyAdminSort(QueryBuilder $qb, string $sortField, string $sortDirection): void
    {
        // PostgreSQL по умолчанию кладёт NULL первым при ORDER BY ... DESC —
        // нам же нужно, чтобы записи без даты всегда были в конце.
        match ($sortField) {
            'status' => $qb->addOrderBy('s.status', $sortDirection),
            'attempts' => $qb->addOrderBy('s.attempts', $sortDirection),
            'fetchedAt' => $qb
                ->addOrderBy('CASE WHEN s.fetchedAt IS NULL THEN 1 ELSE 0 END', 'ASC')
                ->addOrderBy('s.fetchedAt', $sortDirection),
            'lastAttemptAt' => $qb
                ->addOrderBy('CASE WHEN s.lastAttemptAt IS NULL THEN 1 ELSE 0 END', 'ASC')
                ->addOrderBy('s.lastAttemptAt', $sortDirection),
            default => $qb->addOrderBy('s.steamAppId', $sortDirection),
        };
    }

    /**
     * @param array<string, string> $filters
     */
    private function applyAdminFilters(QueryBuilder $qb, array $filters): void
    {
        if (($filters['steamAppId'] ?? '') !== '' && ctype_digit($filters['steamAppId'])) {
            $qb->andWhere('s.steamAppId = :filterSteamAppId')
                ->setParameter('filterSteamAppId', (int) $filters['steamAppId']);
        }

        if (($filters['status'] ?? '') !== '' && SteamGameStatus::tryFrom($filters['status']) !== null) {
            $qb->andWhere('s.status = :filterStatus')
                ->setParameter('filterStatus', SteamGameStatus::from($filters['status']));
        }

        // LOWER() с обеих сторон — LIKE в PostgreSQL по умолчанию регистрозависим.
        if (($filters['game'] ?? '') !== '') {
            $qb->andWhere('LOWER(game.name) LIKE LOWER(:filterGame)')
                ->setParameter('filterGame', '%' . $filters['game'] . '%');
        }
    }
}
