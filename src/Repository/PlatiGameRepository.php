<?php

namespace App\Repository;

use App\Entity\Game;
use App\Entity\PlatiGame;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlatiGame>
 */
class PlatiGameRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности PlatiGame. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlatiGame::class);
    }

    /**
     * Ищет запись конкретного продавца по игре и ссылке — для upsert при
     * повторной проверке (у игры может быть несколько продавцов, см.
     * класс-докблок PlatiGame, поэтому ищем именно по паре game+url, а не
     * просто по игре).
     */
    public function findOneByGameAndUrl(Game $game, string $url): ?PlatiGame
    {
        return $this->findOneBy(['game' => $game, 'url' => $url]);
    }

    /**
     * Все продавцы игры (см. класс-докблок PlatiGame) — для карточки цен на
     * публичной странице игры. По id (порядку нахождения), не по цене:
     * порядок продавцов на странице должен быть стабильным между заходами.
     *
     * @return array<int, PlatiGame>
     */
    public function findByGame(Game $game): array
    {
        return $this->findBy(['game' => $game], ['id' => 'ASC']);
    }

    /**
     * Пачка игр, у которых ещё нет PlatiGame (не проверялись или на
     * прошлой проверке не нашлись — см. класс-докблок PlatiGame), по
     * убыванию Game::popularity — тот же приём, что и
     * SteamGameRepository::findBatchForPriceImport(). LEFT JOIN + IS NULL
     * вместо NOT IN(подзапрос), чтобы Postgres мог использовать
     * анти-джойн по индексу вместо материализации списка уже найденных id.
     *
     * Бесплатные игры (Game::isFree) сразу отсекаются — их не продают на
     * plati.market, поэтому проверять их полнотекстовым поиском смысла
     * нет, только тратится дневной бюджет пачки.
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
            ->leftJoin(PlatiGame::class, 'plati', 'WITH', 'plati.game = g')
            ->andWhere('plati.id IS NULL')
            ->andWhere('g.isFree = false')
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
     * Пачка уже найденных игр (PlatiGame) для обновления цены, по
     * убыванию Game::popularity — тот же приём, что и
     * SteamGameRepository::findBatchForPriceImport(). Keyset-постраничность
     * по составному ключу (popularity, id) — см. findGamesPendingCheck().
     *
     * @return array<int, PlatiGame>
     */
    public function findBatchForPriceImport(?int $afterPopularity, int $afterPlatiGameId, int $limit): array
    {
        $effectivePopularity = 'CASE WHEN game.popularity IS NULL THEN -1 ELSE game.popularity END';

        $qb = $this->createQueryBuilder('p')
            ->addSelect('game')
            ->join('p.game', 'game')
            ->addOrderBy($effectivePopularity, 'DESC')
            ->addOrderBy('p.id', 'ASC')
            ->setMaxResults($limit);

        if ($afterPopularity !== null) {
            $qb->andWhere(
                '(' . $effectivePopularity . ') < :afterPopularity OR '
                . '((' . $effectivePopularity . ') = :afterPopularity AND p.id > :afterPlatiGameId)',
            )
                ->setParameter('afterPopularity', $afterPopularity)
                ->setParameter('afterPlatiGameId', $afterPlatiGameId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Одна страница записей plati.market для таблицы в админке: фильтры по
     * колонкам, сортировка и постраничная навигация — всё на стороне БД.
     * В отличие от GameRepository::findForAdminList() двухшаговый запрос
     * по id не нужен: связь с Game — OneToOne, join не даёт дублей строк
     * (тот же приём, что и в SteamGameRepository::findForAdminList()).
     *
     * @param array<string, string> $filters
     *
     * @return array<int, PlatiGame>
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

        return $qb->addOrderBy('p.id', 'ASC')
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
        $qb = $this->baseAdminQueryBuilder()->select('COUNT(p.id)');
        $this->applyAdminFilters($qb, $filters);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function baseAdminQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('p')->leftJoin('p.game', 'game');
    }

    /**
     * По умолчанию — по убыванию даты нахождения (createdAt): администратору
     * в первую очередь интересно, что нашлось недавно, а не порядок id.
     */
    private function applyAdminSort(QueryBuilder $qb, string $sortField, string $sortDirection): void
    {
        match ($sortField) {
            'updatedAt' => $qb->addOrderBy('p.updatedAt', $sortDirection),
            'game' => $qb->addOrderBy('game.name', $sortDirection),
            'sellerName' => $qb->addOrderBy('p.sellerName', $sortDirection),
            default => $qb->addOrderBy('p.createdAt', $sortDirection),
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
            $qb->andWhere('LOWER(p.url) LIKE LOWER(:filterUrl)')
                ->setParameter('filterUrl', '%' . $filters['url'] . '%');
        }

        if (($filters['sellerName'] ?? '') !== '') {
            $qb->andWhere('LOWER(p.sellerName) LIKE LOWER(:filterSellerName)')
                ->setParameter('filterSellerName', '%' . $filters['sellerName'] . '%');
        }
    }
}
