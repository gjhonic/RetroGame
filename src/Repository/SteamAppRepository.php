<?php

namespace App\Repository;

use App\Entity\SteamApp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
}
