<?php

namespace App\Repository;

use App\Entity\Game;
use App\Service\Game\GameMapper;
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

    /** Общее количество игр (для карточек статистики на дашборде). */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Количество игр по году выхода (для графика на дашборде) — EXTRACT(YEAR ...)
     * не входит в стандартный DQL, поэтому агрегируем нативным SQL.
     *
     * @return array<int, array{year: int, count: int}>
     */
    public function findGamesCountByReleaseYear(): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT EXTRACT(YEAR FROM release_date)::int AS year, COUNT(*) AS count
             FROM game
             WHERE release_date IS NOT NULL
             GROUP BY year
             ORDER BY year',
        );

        return array_map(
            static fn (array $row): array => ['year' => (int) $row['year'], 'count' => (int) $row['count']],
            $rows,
        );
    }

    /**
     * Распределение игр по диапазонам оценки Metacritic (для графика на дашборде).
     *
     * @return array<int, array{label: string, count: int}>
     */
    public function findScoreDistribution(): array
    {
        $buckets = [
            ['label' => '90–100', 'min' => 90, 'max' => 100],
            ['label' => '75–89', 'min' => 75, 'max' => 89],
            ['label' => '50–74', 'min' => 50, 'max' => 74],
            ['label' => '0–49', 'min' => 0, 'max' => 49],
        ];

        $distribution = [];
        foreach ($buckets as $bucket) {
            $distribution[] = [
                'label' => $bucket['label'],
                'count' => (int) $this->createQueryBuilder('g')
                    ->select('COUNT(g.id)')
                    ->andWhere('g.metacriticScore BETWEEN :min AND :max')
                    ->setParameter('min', $bucket['min'])
                    ->setParameter('max', $bucket['max'])
                    ->getQuery()
                    ->getSingleScalarResult(),
            ];
        }

        $distribution[] = [
            'label' => 'Без оценки',
            'count' => (int) $this->createQueryBuilder('g')
                ->select('COUNT(g.id)')
                ->andWhere('g.metacriticScore IS NULL')
                ->getQuery()
                ->getSingleScalarResult(),
        ];

        return $distribution;
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
     * Одна страница публичного каталога: поиск по названию, фильтры по жанру/
     * платформе/диапазону года выхода и сортировка — всё на стороне БД. В
     * отличие от findForAdminList() двухшаговый запрос по id не нужен: фильтр
     * бьёт по одному конкретному жанру/платформе, а связь game-genre/
     * game-platform для конкретной пары уникальна, так что join не даёт
     * дублей строк и GROUP BY не требуется.
     *
     * @param array<string, string> $filters
     *
     * @return array<int, Game>
     */
    public function findForPublicCatalog(
        array $filters,
        string $sortField,
        string $sortDirection,
        int $limit,
        int $offset,
    ): array {
        $qb = $this->createQueryBuilder('g');
        $this->applyPublicFilters($qb, $filters);
        $this->applyPublicSort($qb, $sortField, $sortDirection);

        return $qb->addOrderBy('g.id', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Количество игр публичного каталога, подходящих под фильтры (для расчёта страниц).
     *
     * @param array<string, string> $filters
     */
    public function countForPublicCatalog(array $filters): int
    {
        $qb = $this->createQueryBuilder('g')->select('COUNT(g.id)');
        $this->applyPublicFilters($qb, $filters);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Диапазон годов выхода среди игр с известной датой — используется во
     * фронтенде для ограничения полей "год от"/"год до" в фильтре каталога.
     *
     * @return array{min: int, max: int}|null
     */
    public function findPublicReleaseYearRange(): ?array
    {
        $row = $this->getEntityManager()->getConnection()->fetchAssociative(
            'SELECT EXTRACT(YEAR FROM MIN(release_date))::int AS min, EXTRACT(YEAR FROM MAX(release_date))::int AS max
             FROM game
             WHERE release_date IS NOT NULL',
        );

        return $row === false || $row['min'] === null
            ? null
            : ['min' => (int) $row['min'], 'max' => (int) $row['max']];
    }

    /**
     * @param array<string, string> $filters
     */
    private function applyPublicFilters(QueryBuilder $qb, array $filters): void
    {
        $this->excludeHiddenPublicGenres($qb);

        // LOWER() с обеих сторон — LIKE в PostgreSQL по умолчанию регистрозависим.
        if (($filters['name'] ?? '') !== '') {
            $qb->andWhere('LOWER(g.name) LIKE LOWER(:filterName)')
                ->setParameter('filterName', '%' . $filters['name'] . '%');
        }

        if (($filters['genre'] ?? '') !== '' && ctype_digit($filters['genre'])) {
            $qb->join('g.genres', 'filterGenres')
                ->andWhere('filterGenres.id = :filterGenre')
                ->setParameter('filterGenre', (int) $filters['genre']);
        }

        if (($filters['platform'] ?? '') !== '' && ctype_digit($filters['platform'])) {
            $qb->join('g.platforms', 'filterPlatforms')
                ->andWhere('filterPlatforms.id = :filterPlatform')
                ->setParameter('filterPlatform', (int) $filters['platform']);
        }

        if (($filters['releaseYearFrom'] ?? '') !== '' && ctype_digit($filters['releaseYearFrom'])) {
            $qb->andWhere('g.releaseDate >= :filterReleaseYearFrom')->setParameter(
                'filterReleaseYearFrom',
                new \DateTimeImmutable($filters['releaseYearFrom'] . '-01-01'),
            );
        }

        if (($filters['releaseYearTo'] ?? '') !== '' && ctype_digit($filters['releaseYearTo'])) {
            $qb->andWhere('g.releaseDate < :filterReleaseYearTo')->setParameter(
                'filterReleaseYearTo',
                new \DateTimeImmutable(((int) $filters['releaseYearTo'] + 1) . '-01-01'),
            );
        }
    }

    /** Исключает из публичной выборки игры, у которых есть один из HIDDEN_PUBLIC_GENRE_NAMES. */
    private function excludeHiddenPublicGenres(QueryBuilder $qb): void
    {
        $subQb = $this->getEntityManager()->createQueryBuilder()
            ->select('hg.id')
            ->from(Game::class, 'hg')
            ->join('hg.genres', 'hgg')
            ->where('hgg.name IN (:hiddenGenreNames)');

        $qb->andWhere($qb->expr()->notIn('g.id', $subQb->getDQL()))
            ->setParameter('hiddenGenreNames', GameMapper::HIDDEN_PUBLIC_GENRE_NAMES);
    }

    /**
     * NULL всегда в конце вне зависимости от направления сортировки (PostgreSQL
     * по умолчанию кладёт NULL первым при ORDER BY ... DESC) — игры без
     * popularity/оценки/даты не должны "перебивать" отсортированные.
     */
    private function applyPublicSort(QueryBuilder $qb, string $sortField, string $sortDirection): void
    {
        match ($sortField) {
            'popularity' => $qb
                ->addOrderBy('CASE WHEN g.popularity IS NULL THEN 1 ELSE 0 END', 'ASC')
                ->addOrderBy('g.popularity', $sortDirection),
            'metacriticScore' => $qb
                ->addOrderBy('CASE WHEN g.metacriticScore IS NULL THEN 1 ELSE 0 END', 'ASC')
                ->addOrderBy('g.metacriticScore', $sortDirection),
            'releaseYear' => $qb
                ->addOrderBy('CASE WHEN g.releaseDate IS NULL THEN 1 ELSE 0 END', 'ASC')
                ->addOrderBy('g.releaseDate', $sortDirection),
            default => $qb->addOrderBy('g.name', $sortDirection),
        };
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
