<?php

namespace App\Repository;

use App\Entity\Take;
use App\Entity\TakeComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TakeComment>
 */
class TakeCommentRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности TakeComment. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TakeComment::class);
    }

    /**
     * Одна страница комментариев тэйка, от старых к новым.
     *
     * @return array<int, TakeComment>
     */
    public function findForTake(Take $take, int $limit, int $offset): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.take = :take')
            ->setParameter('take', $take)
            ->addOrderBy('c.createdAt', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /** Количество комментариев тэйка (для расчёта страниц и счётчика). */
    public function countForTake(Take $take): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.take = :take')
            ->setParameter('take', $take)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
