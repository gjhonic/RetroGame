<?php

namespace App\Repository;

use Doctrine\ORM\QueryBuilder;

/**
 * Общая логика постраничного списка для справочников (жанры, разработчики,
 * издатели) в админке: у всех троих одинаковая форма (id, name), отличается
 * только обратная ManyToMany-связь на стороне сущности (Genre::$games,
 * Developer::$games, Publisher::$games — mappedBy соответствующего поля на
 * Game), поэтому JOIN везде идёт через одно и то же имя 'e.games'.
 *
 * @method QueryBuilder createQueryBuilder(string $alias, ?string $indexBy = null)
 */
trait AdminNamedEntityListTrait
{
    /**
     * Одна страница справочника: фильтр по названию, сортировка по названию
     * или количеству игр, постраничная навигация — всё на стороне БД.
     *
     * @param array<string, string> $filters
     *
     * @return array<int, array{id: int, name: string, gamesCount: int}>
     */
    public function findForAdminList(
        array $filters,
        string $sortField,
        string $sortDirection,
        int $limit,
        int $offset,
    ): array {
        $qb = $this->buildAdminListQueryBuilder($filters)
            ->leftJoin('e.games', 'game')
            ->select('e.id AS id', 'e.name AS name', 'COUNT(game.id) AS gamesCount')
            ->groupBy('e.id');

        match ($sortField) {
            'gamesCount' => $qb->addOrderBy('COUNT(game.id)', $sortDirection),
            default => $qb->addOrderBy('e.name', $sortDirection),
        };

        $qb->addOrderBy('e.id', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        /** @var array<int, array{id: int, name: string, gamesCount: string}> $rows */
        $rows = $qb->getQuery()->getResult();

        return array_map(
            static fn (array $row): array => [
                'id' => $row['id'],
                'name' => $row['name'],
                'gamesCount' => (int) $row['gamesCount'],
            ],
            $rows,
        );
    }

    /**
     * Без JOIN на игры — фильтр только по названию, сам список игр тут не нужен.
     *
     * @param array<string, string> $filters
     */
    public function countForAdminList(array $filters): int
    {
        $qb = $this->buildAdminListQueryBuilder($filters)->select('COUNT(e.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param array<string, string> $filters
     */
    private function buildAdminListQueryBuilder(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('e');

        if (($filters['name'] ?? '') !== '') {
            // LOWER() с обеих сторон — LIKE в PostgreSQL по умолчанию регистрозависим.
            $qb->andWhere('LOWER(e.name) LIKE LOWER(:filterName)')
                ->setParameter('filterName', '%' . $filters['name'] . '%');
        }

        return $qb;
    }
}
