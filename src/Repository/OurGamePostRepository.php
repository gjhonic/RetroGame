<?php

namespace App\Repository;

use App\Entity\Enum\OurGamePostType;
use App\Entity\Enum\OurGameStatus;
use App\Entity\OurGamePost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OurGamePost>
 */
class OurGamePostRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности OurGamePost. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OurGamePost::class);
    }

    /**
     * Одна страница постов для таблицы в админке: фильтры по колонкам,
     * сортировка и постраничная навигация. game/author — *ToOne, поэтому
     * fetch-join с LIMIT безопасен (в отличие от OurGameRepository, где
     * жанры — коллекция).
     *
     * @param array<string, string> $filters
     *
     * @return array<int, OurGamePost>
     */
    public function findForAdminList(
        array $filters,
        string $sortField,
        string $sortDirection,
        int $limit,
        int $offset,
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('g', 'a')
            ->join('p.game', 'g')
            ->join('p.author', 'a');
        $this->applyAdminFilters($qb, $filters);
        $this->applyAdminSort($qb, $sortField, $sortDirection);

        $qb->addOrderBy('p.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        return $qb->getQuery()->getResult();
    }

    /**
     * Количество постов, подходящих под фильтры (для расчёта страниц).
     *
     * @param array<string, string> $filters
     */
    public function countForAdminList(array $filters): int
    {
        $qb = $this->createQueryBuilder('p')->select('COUNT(p.id)');
        $this->applyAdminFilters($qb, $filters);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Все посты об игре для блока "Посты" на странице игры в админке.
     *
     * @return array<int, OurGamePost>
     */
    public function findByGame(int $gameId): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('a')
            ->join('p.author', 'a')
            ->where('p.game = :gameId')
            ->setParameter('gameId', $gameId)
            ->addOrderBy('p.postedAt', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Одна страница опубликованных постов для публичного API/кабинета:
     * фильтры по игре/типу, сортировка и постраничная навигация.
     *
     * @param array<string, string> $filters
     *
     * @return array<int, OurGamePost>
     */
    public function findPublishedForPublic(
        array $filters,
        string $sortField,
        string $sortDirection,
        int $limit,
        int $offset,
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('g', 'a')
            ->join('p.game', 'g')
            ->join('p.author', 'a')
            ->where('p.status = :status')
            ->setParameter('status', OurGameStatus::Published);
        $this->applyAdminFilters($qb, $filters);
        $this->applyAdminSort($qb, $sortField, $sortDirection);

        $qb->addOrderBy('p.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        return $qb->getQuery()->getResult();
    }

    /**
     * Количество опубликованных постов, подходящих под фильтры (для расчёта страниц).
     *
     * @param array<string, string> $filters
     */
    public function countPublishedForPublic(array $filters): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.status = :status')
            ->setParameter('status', OurGameStatus::Published);
        $this->applyAdminFilters($qb, $filters);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** Ищет опубликованный пост по id (для публичного API — черновики не отдаём). */
    public function findOnePublishedById(int $id): ?OurGamePost
    {
        return $this->createQueryBuilder('p')
            ->addSelect('g', 'a')
            ->join('p.game', 'g')
            ->join('p.author', 'a')
            ->where('p.id = :id')
            ->andWhere('p.status = :status')
            ->setParameter('id', $id)
            ->setParameter('status', OurGameStatus::Published)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function applyAdminSort(QueryBuilder $qb, string $sortField, string $sortDirection): void
    {
        match ($sortField) {
            'type' => $qb->addOrderBy('p.type', $sortDirection),
            'status' => $qb->addOrderBy('p.status', $sortDirection),
            'game' => $qb->addOrderBy('g.name', $sortDirection),
            default => $qb->addOrderBy('p.postedAt', $sortDirection),
        };
    }

    /**
     * @param array<string, string> $filters
     */
    private function applyAdminFilters(QueryBuilder $qb, array $filters): void
    {
        if (($filters['game'] ?? '') !== '' && ctype_digit($filters['game'])) {
            $qb->andWhere('p.game = :filterGame')
                ->setParameter('filterGame', (int) $filters['game']);
        }

        if (($filters['type'] ?? '') !== '' && OurGamePostType::tryFrom($filters['type']) !== null) {
            $qb->andWhere('p.type = :filterType')
                ->setParameter('filterType', OurGamePostType::from($filters['type']));
        }

        if (($filters['status'] ?? '') !== '' && OurGameStatus::tryFrom($filters['status']) !== null) {
            $qb->andWhere('p.status = :filterStatus')
                ->setParameter('filterStatus', OurGameStatus::from($filters['status']));
        }
    }
}
