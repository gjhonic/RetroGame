<?php

namespace App\Repository;

use App\Entity\Enum\OurGameStatus;
use App\Entity\OurGame;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OurGame>
 */
class OurGameRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности OurGame. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OurGame::class);
    }

    /** Ищет игру по slug. */
    public function findOneBySlug(string $slug): ?OurGame
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * Опубликованные игры для публичной витрины /our-games (в кабинете отдельной
     * страницы нет — ссылка "Наши игры" в сайдбаре ведёт сюда же): новые
     * (по дате выхода) сначала, без даты — в конце, дальше по названию.
     *
     * @return array<int, OurGame>
     */
    public function findPublishedForPublic(): array
    {
        return $this->createQueryBuilder('g')
            ->addSelect('genres')
            ->leftJoin('g.genres', 'genres')
            ->where('g.status = :status')
            ->setParameter('status', OurGameStatus::Published)
            ->addOrderBy('CASE WHEN g.releaseDate IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('g.releaseDate', 'DESC')
            ->addOrderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Ищет опубликованную игру по slug (для публичной витрины — черновики не отдаём). */
    public function findOnePublishedBySlug(string $slug): ?OurGame
    {
        return $this->createQueryBuilder('g')
            ->addSelect('genres', 'downloadLinks')
            ->leftJoin('g.genres', 'genres')
            ->leftJoin('g.downloadLinks', 'downloadLinks')
            ->where('g.slug = :slug')
            ->andWhere('g.status = :status')
            ->setParameter('slug', $slug)
            ->setParameter('status', OurGameStatus::Published)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Одна страница своих игр для таблицы в админке: фильтры по колонкам,
     * сортировка и постраничная навигация — всё на стороне БД. Жанры
     * подгружаются отдельным запросом по id (см. GameRepository::findForAdminList()
     * — тот же приём, LIMIT несовместим с одновременным fetch-join коллекций).
     *
     * @param array<string, string> $filters
     *
     * @return array<int, OurGame>
     */
    public function findForAdminList(
        array $filters,
        string $sortField,
        string $sortDirection,
        int $limit,
        int $offset,
    ): array {
        $ids = $this->findAdminListIds($filters, $sortField, $sortDirection, $limit, $offset);

        if ($ids === []) {
            return [];
        }

        $games = $this->createQueryBuilder('g')
            ->addSelect('genres')
            ->leftJoin('g.genres', 'genres')
            ->where('g.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $gamesById = [];
        foreach ($games as $game) {
            $gamesById[$game->getId()] = $game;
        }

        return array_map(static fn (int $id): OurGame => $gamesById[$id], $ids);
    }

    /**
     * Количество своих игр, подходящих под фильтры (для расчёта страниц).
     *
     * @param array<string, string> $filters
     */
    public function countForAdminList(array $filters): int
    {
        $qb = $this->createQueryBuilder('g')->select('COUNT(DISTINCT g.id)');
        $this->applyAdminFilters($qb, $filters);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param array<string, string> $filters
     *
     * @return array<int, int>
     */
    private function findAdminListIds(
        array $filters,
        string $sortField,
        string $sortDirection,
        int $limit,
        int $offset,
    ): array {
        $qb = $this->createQueryBuilder('g')->select('g.id')->groupBy('g.id');
        $this->applyAdminFilters($qb, $filters);
        $this->applyAdminSort($qb, $sortField, $sortDirection);

        $qb->addOrderBy('g.id', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        return array_column($qb->getQuery()->getScalarResult(), 'id');
    }

    private function applyAdminSort(QueryBuilder $qb, string $sortField, string $sortDirection): void
    {
        // PostgreSQL по умолчанию кладёт NULL первым при ORDER BY ... DESC —
        // нам же нужно, чтобы игры без даты выхода/версии всегда были в конце.
        match ($sortField) {
            'status' => $qb->addOrderBy('g.status', $sortDirection),
            'releaseDate' => $qb
                ->addOrderBy('CASE WHEN g.releaseDate IS NULL THEN 1 ELSE 0 END', 'ASC')
                ->addOrderBy('g.releaseDate', $sortDirection),
            'createdAt' => $qb->addOrderBy('g.createdAt', $sortDirection),
            default => $qb->addOrderBy('g.name', $sortDirection),
        };
    }

    /**
     * @param array<string, string> $filters
     */
    private function applyAdminFilters(QueryBuilder $qb, array $filters): void
    {
        // LOWER() с обеих сторон — LIKE в PostgreSQL по умолчанию регистрозависим.
        if (($filters['name'] ?? '') !== '') {
            $qb->andWhere('LOWER(g.name) LIKE LOWER(:filterName)')
                ->setParameter('filterName', '%' . $filters['name'] . '%');
        }

        if (($filters['status'] ?? '') !== '' && OurGameStatus::tryFrom($filters['status']) !== null) {
            $qb->andWhere('g.status = :filterStatus')
                ->setParameter('filterStatus', OurGameStatus::from($filters['status']));
        }

        if (($filters['genre'] ?? '') !== '' && ctype_digit($filters['genre'])) {
            $qb->join('g.genres', 'filterGenres')
                ->andWhere('filterGenres.id = :filterGenre')
                ->setParameter('filterGenre', (int) $filters['genre']);
        }
    }
}
