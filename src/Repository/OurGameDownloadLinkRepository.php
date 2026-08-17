<?php

namespace App\Repository;

use App\Entity\OurGameDownloadLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OurGameDownloadLink>
 */
class OurGameDownloadLinkRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности OurGameDownloadLink. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OurGameDownloadLink::class);
    }
}
