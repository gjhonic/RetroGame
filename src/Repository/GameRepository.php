<?php

namespace App\Repository;

use App\Entity\Game;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game>
 */
class GameRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности Game. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    /**
     * Пути обложек для декоративного фона (страница входа и т.п.), в случайном порядке.
     *
     * @return array<int, string>
     */
    public function findRandomCoverImagePaths(int $limit): array
    {
        /** @var array<int, string> $paths */
        $paths = $this->createQueryBuilder('g')
            ->select('g.coverImagePath')
            ->where('g.coverImagePath IS NOT NULL')
            ->getQuery()
            ->getSingleColumnResult();

        shuffle($paths);

        return array_slice($paths, 0, $limit);
    }
}
