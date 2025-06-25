<?php

namespace App\Repository;

use App\Entity\Game;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
    public function findByFilters(?string $search, ?int $genreId): array
    {
        $qb = $this->createQueryBuilder('g')
            ->leftJoin('g.genre', 'genre')
            ->addSelect('genre');

        if ($search != null) {
            $qb->andWhere('g.name LIKE :search')->setParameter('search', '%' . $search . '%');
        }

        if ($genreId != null) {
            $qb->andWhere('genre.id = :genreId')->setParameter('genreId', $genreId);
        }

        return $qb->getQuery()->getResult();
    }
}
