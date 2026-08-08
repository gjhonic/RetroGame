<?php

namespace App\Repository;

use App\Entity\Developer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Developer>
 */
class DeveloperRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности Developer. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Developer::class);
    }
}
