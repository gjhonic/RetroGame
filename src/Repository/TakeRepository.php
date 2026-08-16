<?php

namespace App\Repository;

use App\Entity\Take;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Take>
 */
class TakeRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности Take. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Take::class);
    }

    /**
     * Одна страница публичного списка тэйков: опциональный фильтр по игре,
     * сортировка по дате создания.
     *
     * @param array<string, string> $filters
     *
     * @return array<int, Take>
     */
    public function findForPublicList(
        array $filters,
        string $sortField,
        string $sortDirection,
        int $limit,
        int $offset,
    ): array {
        $qb = $this->createQueryBuilder('t');
        $this->applyPublicFilters($qb, $filters);
        $this->applyPublicSort($qb, $sortField, $sortDirection);

        return $qb->addOrderBy('t.id', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Количество тэйков публичного списка, подходящих под фильтры (для расчёта страниц).
     *
     * @param array<string, string> $filters
     */
    public function countForPublicList(array $filters): int
    {
        $qb = $this->createQueryBuilder('t')->select('COUNT(t.id)');
        $this->applyPublicFilters($qb, $filters);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Одна страница личной ленты автора: опционально не раньше $since, новые тэйки сначала.
     *
     * @return array<int, Take>
     */
    public function findForAuthor(User $author, ?\DateTimeImmutable $since, int $limit, int $offset): array
    {
        $qb = $this->createAuthorQueryBuilder($author, $since)
            ->addOrderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        return $qb->getQuery()->getResult();
    }

    /** Количество тэйков автора, подходящих под фильтр $since (для расчёта страниц). */
    public function countForAuthor(User $author, ?\DateTimeImmutable $since): int
    {
        $qb = $this->createAuthorQueryBuilder($author, $since)->select('COUNT(t.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function createAuthorQueryBuilder(User $author, ?\DateTimeImmutable $since): QueryBuilder
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.author = :author')
            ->setParameter('author', $author);

        if ($since !== null) {
            $qb->andWhere('t.createdAt >= :since')->setParameter('since', $since);
        }

        return $qb;
    }

    /**
     * @param array<string, string> $filters
     */
    private function applyPublicFilters(QueryBuilder $qb, array $filters): void
    {
        if (($filters['game'] ?? '') !== '' && ctype_digit($filters['game'])) {
            $qb->andWhere('t.game = :filterGame')->setParameter('filterGame', (int) $filters['game']);
        }
    }

    /** MVP: единственное поддерживаемое поле сортировки — createdAt. */
    private function applyPublicSort(QueryBuilder $qb, string $sortField, string $sortDirection): void
    {
        match ($sortField) {
            default => $qb->addOrderBy('t.createdAt', $sortDirection),
        };
    }
}
