<?php

namespace App\Repository;

use App\Entity\Platform;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Platform>
 */
class PlatformRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности Platform. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Platform::class);
    }
}
