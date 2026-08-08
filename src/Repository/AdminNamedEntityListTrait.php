<?php

namespace App\Repository;

use App\Entity\Game;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

/**
 * Общая логика постраничного списка для справочников (жанры, разработчики,
 * издатели) в админке: у всех троих одинаковая форма (id, name), отличается
 * только имя ManyToMany-связи на стороне Game (genres/developers/publishers),
 * которое возвращает gameAssociationName().
 *
 * @method QueryBuilder createQueryBuilder(string $alias, ?string $indexBy = null)
 */
trait AdminNamedEntityListTrait
{
    /** Имя ManyToMany-связи на Game, которой принадлежит эта сущность. */
    abstract protected function gameAssociationName(): string;

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
     * @param array<string, string> $filters
     */
    public function countForAdminList(array $filters): int
    {
        $qb = $this->buildAdminListQueryBuilder($filters)->select('COUNT(DISTINCT e.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param array<string, string> $filters
     */
    private function buildAdminListQueryBuilder(array $filters): QueryBuilder
    {
        // LEFT JOIN без общей владеющей стороны — Game хранит ManyToMany
        // однонаправленно, поэтому связь проверяется через MEMBER OF.
        $qb = $this->createQueryBuilder('e')
            ->leftJoin(Game::class, 'game', Join::WITH, sprintf('e MEMBER OF game.%s', $this->gameAssociationName()));

        if (($filters['name'] ?? '') !== '') {
            // LOWER() с обеих сторон — LIKE в PostgreSQL по умолчанию регистрозависим.
            $qb->andWhere('LOWER(e.name) LIKE LOWER(:filterName)')
                ->setParameter('filterName', '%' . $filters['name'] . '%');
        }

        return $qb;
    }
}
