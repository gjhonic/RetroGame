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

    /**
     * Возвращает статистику по количеству импортированных цен по дням.
     * @return array [ ['date' => '2024-07-07', 'count' => 123], ... ]
     */
    public function getImportStatsByDay(?\DateTimeInterface $dateFrom = null, ?\DateTimeInterface $dateTo = null): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $params = [];
        $where = [];
        if ($dateFrom) {
            $where[] = 'updated_at >= :dateFrom';
            $params['dateFrom'] = $dateFrom->format('Y-m-d 00:00:00');
        }
        if ($dateTo) {
            $where[] = 'updated_at <= :dateTo';
            $params['dateTo'] = $dateTo->format('Y-m-d 23:59:59');
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "
            SELECT DATE(updated_at) as date, COUNT(id) as count
            FROM game_shop_price_history
            $whereSql
            GROUP BY date
            ORDER BY date DESC
        ";
        return $conn->executeQuery($sql, $params)->fetchAllAssociative();
    }
}
