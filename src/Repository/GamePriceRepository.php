<?php

namespace App\Repository;

use App\Entity\Game;
use App\Entity\GamePrice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GamePrice>
 */
class GamePriceRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности GamePrice. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GamePrice::class);
    }

    /** Ищет снимок цены игры за конкретный день — для upsert при повторном импорте в тот же день. */
    public function findOneByGameAndDate(Game $game, \DateTimeImmutable $date): ?GamePrice
    {
        return $this->findOneBy(['game' => $game, 'date' => $date]);
    }

    /**
     * Полная история цены игры по дням, от старых к новым — источник и для
     * графика на карточке игры, и для текущей цены (последний элемент списка).
     *
     * @return array<int, GamePrice>
     */
    public function findHistoryForGame(Game $game): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.game = :game')
            ->setParameter('game', $game)
            ->addOrderBy('p.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Все снимки цен за период [from, to], упорядоченные по игре и дате —
     * для GamePriceCleanupService: последовательные записи одной игры идут
     * подряд, что позволяет группировать их без отдельного запроса на игру.
     *
     * @return array<int, GamePrice>
     */
    public function findAllInRangeOrderedByGame(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.date >= :from')
            ->andWhere('p.date <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->addOrderBy('p.game', 'ASC')
            ->addOrderBy('p.date', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
