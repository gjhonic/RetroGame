<?php

namespace App\Repository;

use App\Entity\GameShopPriceHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameShopPriceHistory>
 *
 * @method GameShopPriceHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method GameShopPriceHistory|null findOneBy($criteria, $orderBy = null)
 * @method GameShopPriceHistory[]    findAll()
 * @method GameShopPriceHistory[]    findBy($criteria, $orderBy = null, $limit = null, $offset = null)
 */
class GameShopPriceHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameShopPriceHistory::class);
    }
}
