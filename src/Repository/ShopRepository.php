<?php

namespace App\Repository;

use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Shop>
 *
 * @method Shop|null find($id, $lockMode = null, $lockVersion = null)
 * @method Shop|null findOneBy($criteria, $orderBy = null)
 * @method Shop[]    findAll()
 * @method Shop[]    findBy($criteria, $orderBy = null, int|null $limit = null, int|null $offset = null)
 */
class ShopRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shop::class);
    }

    /**
     * Возвращает все магазины с количеством игр в каждом.
     *
     * @return list<array<string, mixed>>
     * @throws \Doctrine\DBAL\Exception
     */
    public function getShopsWithGameCount(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = "
            SELECT s.id, s.name, s.image, COUNT(gs.id) as game_count
            FROM shops s
            LEFT JOIN game_shop gs ON s.id = gs.shop_id
            GROUP BY s.id, s.name, s.image
            ORDER BY game_count DESC, s.name
        ";
        return $conn->executeQuery($sql)->fetchAllAssociative();
    }

    //    /**
    //     * @return Shop[] Returns an array of Shop objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Shop
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
