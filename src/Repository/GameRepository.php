<?php

namespace App\Repository;

use App\Entity\Game;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game>
 */
class GameRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности Game. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    /**
     * Пути обложек для декоративного фона (страница входа и т.п.), в случайном порядке.
     *
     * @return array<int, string>
     */
    public function findRandomCoverImagePaths(int $limit): array
    {
        /** @var array<int, string> $paths */
        $paths = $this->createQueryBuilder('g')
            ->select('g.coverImagePath')
            ->where('g.coverImagePath IS NOT NULL')
            ->getQuery()
            ->getSingleColumnResult();

        shuffle($paths);

        return array_slice($paths, 0, $limit);
    }

    /**
     * Одна страница игр для таблицы в админке: фильтры по колонкам, сортировка
     * и постраничная навигация — всё на стороне БД, чтобы не тянуть в браузер
     * весь каталог. Разработчики/издатели/жанры подгружаются отдельным запросом
     * по id (см. findAdminListIds()), т.к. LIMIT несовместим с одновременным
     * fetch-join коллекций (см. Doctrine Pagination).
     *
     * @param array<string, string> $filters
     *
     * @return array<int, Game>
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
            ->addSelect('developers', 'publishers', 'genres')
            ->leftJoin('g.developers', 'developers')
            ->leftJoin('g.publishers', 'publishers')
            ->leftJoin('g.genres', 'genres')
            ->where('g.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $gamesById = [];
        foreach ($games as $game) {
            $gamesById[$game->getId()] = $game;
        }

        return array_map(static fn (int $id): Game => $gamesById[$id], $ids);
    }

    /**
     * Количество игр, подходящих под фильтры (для расчёта страниц).
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
        // GROUP BY (а не SELECT DISTINCT), чтобы можно было сортировать по
        // полям g.*, не входящим в SELECT: в PostgreSQL при группировке по
        // первичному ключу это разрешено благодаря функциональной зависимости.
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
        // нам же нужно, чтобы игры без оценки/даты всегда были в конце.
        match ($sortField) {
            'metacriticScore' => $qb
                ->addOrderBy('CASE WHEN g.metacriticScore IS NULL THEN 1 ELSE 0 END', 'ASC')
                ->addOrderBy('g.metacriticScore', $sortDirection),
            'releaseYear' => $qb
                ->addOrderBy('CASE WHEN g.releaseDate IS NULL THEN 1 ELSE 0 END', 'ASC')
                ->addOrderBy('g.releaseDate', $sortDirection),
            // Игра может иметь несколько разработчиков/издателей — сортируем
            // по первому по алфавиту (MIN допустим в ORDER BY при GROUP BY g.id).
            'developers' => $qb
                ->leftJoin('g.developers', 'sortDevelopers')
                ->addOrderBy('MIN(sortDevelopers.name)', $sortDirection),
            'publishers' => $qb
                ->leftJoin('g.publishers', 'sortPublishers')
                ->addOrderBy('MIN(sortPublishers.name)', $sortDirection),
            default => $qb->addOrderBy('g.name', $sortDirection),
        };
    }

    /**
     * @param array<string, string> $filters
     */
    private function applyAdminFilters(QueryBuilder $qb, array $filters): void
    {
        // LOWER() с обеих сторон — LIKE в PostgreSQL по умолчанию регистрозависим,
        // а пользователь может ввести фильтр в любом регистре.
        if (($filters['name'] ?? '') !== '') {
            $qb->andWhere('LOWER(g.name) LIKE LOWER(:filterName)')
                ->setParameter('filterName', '%' . $filters['name'] . '%');
        }

        if (($filters['developer'] ?? '') !== '') {
            $qb->leftJoin('g.developers', 'filterDevelopers')
                ->andWhere('LOWER(filterDevelopers.name) LIKE LOWER(:filterDeveloper)')
                ->setParameter('filterDeveloper', '%' . $filters['developer'] . '%');
        }

        if (($filters['publisher'] ?? '') !== '') {
            $qb->leftJoin('g.publishers', 'filterPublishers')
                ->andWhere('LOWER(filterPublishers.name) LIKE LOWER(:filterPublisher)')
                ->setParameter('filterPublisher', '%' . $filters['publisher'] . '%');
        }

        if (($filters['genre'] ?? '') !== '') {
            $qb->leftJoin('g.genres', 'filterGenres')
                ->andWhere('LOWER(filterGenres.name) LIKE LOWER(:filterGenre)')
                ->setParameter('filterGenre', '%' . $filters['genre'] . '%');
        }

        if (($filters['metacriticScore'] ?? '') !== '' && ctype_digit($filters['metacriticScore'])) {
            $qb->andWhere('g.metacriticScore = :filterMetacriticScore')
                ->setParameter('filterMetacriticScore', (int) $filters['metacriticScore']);
        }

        if (($filters['releaseYear'] ?? '') !== '' && ctype_digit($filters['releaseYear'])) {
            // YEAR() не входит в стандартный DQL — сравниваем полуоткрытым диапазоном дат.
            $year = (int) $filters['releaseYear'];
            $qb->andWhere('g.releaseDate >= :filterReleaseYearStart AND g.releaseDate < :filterReleaseYearEnd')
                ->setParameter('filterReleaseYearStart', new \DateTimeImmutable($year . '-01-01'))
                ->setParameter('filterReleaseYearEnd', new \DateTimeImmutable(($year + 1) . '-01-01'));
        }
    }
}
