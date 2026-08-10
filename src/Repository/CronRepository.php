<?php

namespace App\Repository;

use App\Entity\Cron;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cron>
 */
class CronRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cron::class);
    }

    public function findOneByCommand(string $command): ?Cron
    {
        return $this->findOneBy(['command' => $command]);
    }

    /** @return list<Cron> */
    public function findAllOrderedByCommand(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.command', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
