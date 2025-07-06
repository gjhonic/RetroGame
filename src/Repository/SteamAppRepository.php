<?php

namespace App\Repository;

use App\Entity\SteamApp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SteamApp>
 *
 * @method SteamApp|null find($id, $lockMode = null, $lockVersion = null)
 * @method SteamApp|null findOneBy($criteria, $orderBy = null)
 * @method SteamApp[]    findAll()
 * @method SteamApp[]    findBy($criteria, $orderBy = null, $limit = null, $offset = null)
 */
class SteamAppRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SteamApp::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function findSteamAppsByFilters(
        ?string $search,
        ?string $name,
        ?string $type,
        int $page = 1,
        int $limit = 40,
        string $sort = 'createdAt',
        string $direction = 'desc'
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $allowedSorts = ['app_id', 'type', 'createdAt'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'createdAt';
        $direction = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';
        $qb->orderBy('s.' . $sort, $direction);

        if ($search) {
            $qb->andWhere('s.app_id = :search')
                ->setParameter('search', (int) $search);
        }

        if ($name) {
            $qb->andWhere('s.name LIKE :name')
                ->setParameter('name', '%' . $name . '%');
        }

        if ($type) {
            $qb->andWhere('s.type = :type')
                ->setParameter('type', $type);
        }

        $paginator = new Paginator($qb, true);

        return iterator_to_array($paginator);
    }

    public function countByFilters(?string $search, ?string $name, ?string $type): int
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)');

        if ($search) {
            $qb->andWhere('s.app_id = :search')
                ->setParameter('search', (int) $search);
        }

        if ($name) {
            $qb->andWhere('s.name LIKE :name')
                ->setParameter('name', '%' . $name . '%');
        }

        if ($type) {
            $qb->andWhere('s.type = :type')
                ->setParameter('type', $type);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
