<?php

namespace App\Repository;

use App\Entity\Game;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game>
 *
 * @method Game|null find($id, $lockMode = null, $lockVersion = null)
 * @method Game|null findOneBy($criteria, $orderBy = null)
 * @method Game[]    findAll()
 * @method Game[]    findBy($criteria, $orderBy = null, $limit = null, $offset = null)
 */
class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function findByFilters(?string $search, ?int $genreId, int $page = 1, int $limit = 40): array
    {
        $qb = $this->createQueryBuilder('g')
            ->distinct()
            ->leftJoin('g.genre', 'genre')
            ->addSelect('genre')
            ->leftJoin('g.shops', 'shops')
            ->addSelect('shops')
            ->leftJoin('shops.shop', 'shop')
            ->addSelect('shop')
            ->orderBy('g.ownersCount', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if ($search) {
            $qb->andWhere('g.name LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($genreId) {
            $qb->andWhere(':genreId MEMBER OF g.genre')
                ->setParameter('genreId', $genreId);
        }

        $paginator = new Paginator($qb, true);
        return iterator_to_array($paginator);
    }

    public function countByFilters(?string $search, ?int $genreId): int
    {
        $qb = $this->createQueryBuilder('g')
            ->select('COUNT(DISTINCT g.id)')
            ->leftJoin('g.genre', 'genre');

        if ($search) {
            $qb->andWhere('g.name LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($genreId) {
            $qb->andWhere(':genreId MEMBER OF g.genre')
                ->setParameter('genreId', $genreId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
