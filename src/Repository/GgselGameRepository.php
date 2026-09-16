<?php

namespace App\Repository;

use App\Entity\Game;
use App\Entity\GgselGame;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GgselGame>
 */
class GgselGameRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности GgselGame. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GgselGame::class);
    }

    /** Ищет запись по игре (для точечных проверок/админки). */
    public function findOneByGame(Game $game): ?GgselGame
    {
        return $this->findOneBy(['game' => $game]);
    }

    /**
     * Пачка игр, у которых ещё нет GgselGame (не проверялись или на
     * прошлой проверке не нашлись — см. класс-докблок GgselGame), по
     * убыванию Game::popularity — тот же приём, что и
     * SteamGameRepository::findBatchForPriceImport(). LEFT JOIN + IS NULL
     * вместо NOT IN(подзапрос), чтобы Postgres мог использовать
     * анти-джойн по индексу вместо материализации списка уже найденных id.
     *
     * Keyset-постраничность по составному ключу (popularity, id):
     * $afterPopularity === null означает "начать с самых популярных".
     * NULL popularity трактуется как -1 — такие игры идут в конце очереди
     * (тот же приём, что и в GameRepository::applyPublicSort()).
     *
     * @return array<int, Game>
     */
    public function findGamesPendingCheck(?int $afterPopularity, int $afterGameId, int $limit): array
    {
        $effectivePopularity = 'CASE WHEN g.popularity IS NULL THEN -1 ELSE g.popularity END';

        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('g')
            ->from(Game::class, 'g')
            ->leftJoin(GgselGame::class, 'ggsel', 'WITH', 'ggsel.game = g')
            ->andWhere('ggsel.id IS NULL')
            ->addOrderBy($effectivePopularity, 'DESC')
            ->addOrderBy('g.id', 'ASC')
            ->setMaxResults($limit);

        if ($afterPopularity !== null) {
            $qb->andWhere(
                '(' . $effectivePopularity . ') < :afterPopularity OR '
                . '((' . $effectivePopularity . ') = :afterPopularity AND g.id > :afterGameId)',
            )
                ->setParameter('afterPopularity', $afterPopularity)
                ->setParameter('afterGameId', $afterGameId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Одна страница записей ggsel.net для таблицы в админке: фильтры по
     * колонкам, сортировка и постраничная навигация — всё на стороне БД.
     * В отличие от GameRepository::findForAdminList() двухшаговый запрос
     * по id не нужен: связь с Game — OneToOne, join не даёт дублей строк
     * (тот же приём, что и в SteamGameRepository::findForAdminList()).
     *
     * @param array<string, string> $filters
     *
     * @return array<int, GgselGame>
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

        return $qb->addOrderBy('gg.id', 'ASC')
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
        $qb = $this->baseAdminQueryBuilder()->select('COUNT(gg.id)');
        $this->applyAdminFilters($qb, $filters);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function baseAdminQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('gg')->leftJoin('gg.game', 'game');
    }

    /**
     * По умолчанию — по убыванию даты нахождения (createdAt): администратору
     * в первую очередь интересно, что нашлось недавно, а не порядок id.
     */
    private function applyAdminSort(QueryBuilder $qb, string $sortField, string $sortDirection): void
    {
        match ($sortField) {
            'updatedAt' => $qb->addOrderBy('gg.updatedAt', $sortDirection),
            'game' => $qb->addOrderBy('game.name', $sortDirection),
            default => $qb->addOrderBy('gg.createdAt', $sortDirection),
        };
    }

    /**
     * @param array<string, string> $filters
     */
    private function applyAdminFilters(QueryBuilder $qb, array $filters): void
    {
        // LOWER() с обеих сторон — LIKE в PostgreSQL по умолчанию регистрозависим.
        if (($filters['game'] ?? '') !== '') {
            $qb->andWhere('LOWER(game.name) LIKE LOWER(:filterGame)')
                ->setParameter('filterGame', '%' . $filters['game'] . '%');
        }

        if (($filters['url'] ?? '') !== '') {
            $qb->andWhere('LOWER(gg.url) LIKE LOWER(:filterUrl)')
                ->setParameter('filterUrl', '%' . $filters['url'] . '%');
        }
    }
}
