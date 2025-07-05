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

    public function findWithRelations(int $id): ?GameShop
    {
        return $this->createQueryBuilder('gs')
            ->leftJoin('gs.game', 'g')->addSelect('g')
            ->leftJoin('gs.shop', 's')->addSelect('s')
            ->where('gs.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Возвращает статистику импорта игр по дням и площадкам.
     * @return list<array<string, mixed>>
     */
    public function getImportStatsByDayAndShop(
        ?\DateTimeInterface $dateFrom = null,
        ?\DateTimeInterface $dateTo = null
    ): array {
        $conn = $this->getEntityManager()->getConnection();
        $params = [];
        $where = [];
        if ($dateFrom) {
            $where[] = 'gs.created_at >= :dateFrom';
            $params['dateFrom'] = $dateFrom->format('Y-m-d 00:00:00');
        }
        if ($dateTo) {
            $where[] = 'gs.created_at <= :dateTo';
            $params['dateTo'] = $dateTo->format('Y-m-d 23:59:59');
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "
            SELECT DATE(gs.created_at) as date, s.name as shop, s.id as shop_id, COUNT(gs.id) as count
            FROM game_shop gs
            INNER JOIN shops s ON gs.shop_id = s.id
            $whereSql
            GROUP BY date, shop, shop_id
            ORDER BY date DESC, shop
        ";
        return $conn->executeQuery($sql, $params)->fetchAllAssociative();
    }

    /**
     * Возвращает общее количество игр по торговым площадкам.
     * @return list<array<string, mixed>>
     */
    public function getTotalGamesByShop(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = "
            SELECT s.name as shop, s.id as shop_id, COUNT(gs.id) as count
            FROM game_shop gs
            INNER JOIN shops s ON gs.shop_id = s.id
            GROUP BY shop, shop_id
            ORDER BY count DESC, shop
        ";
        return $conn->executeQuery($sql)->fetchAllAssociative();
    }
}
