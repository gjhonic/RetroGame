<?php

namespace App\Repository;

use App\Entity\Genre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Genre>
 */
class GenreRepository extends ServiceEntityRepository
{
    use AdminNamedEntityListTrait;

    /** Регистрирует репозиторий для сущности Genre. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Genre::class);
    }

    protected function gameAssociationName(): string
    {
        return 'genres';
    }
}
