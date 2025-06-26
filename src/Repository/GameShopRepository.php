<?php

namespace App\Repository;

use App\Entity\GameShop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameShop>
 *
 * @method GameShop|null find($id, $lockMode = null, $lockVersion = null)
 * @method GameShop|null findOneBy($criteria, $orderBy = null)
 * @method GameShop[]    findAll()
 * @method GameShop[]    findBy($criteria, $orderBy = null, $limit = null, $offset = null)
 */
class GameShopRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameShop::class);
    }
}
