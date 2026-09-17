<?php

namespace App\Repository;

use App\Entity\PlatiGame;
use App\Entity\PlatiGamePrice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlatiGamePrice>
 */
class PlatiGamePriceRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности PlatiGamePrice. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlatiGamePrice::class);
    }

    /** Ищет снимок цены продавца за конкретный день — для upsert при повторном импорте в тот же день. */
    public function findOneByPlatiGameAndDate(PlatiGame $platiGame, \DateTimeImmutable $date): ?PlatiGamePrice
    {
        return $this->findOneBy(['platiGame' => $platiGame, 'date' => $date]);
    }

    /**
     * Полная история цены продавца по дням, от старых к новым — источник
     * для графика на карточке игры (см. GamePriceRepository::findHistoryForGame()
     * для Steam-аналога).
     *
     * @return array<int, PlatiGamePrice>
     */
    public function findHistoryForPlatiGame(PlatiGame $platiGame): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.platiGame = :platiGame')
            ->setParameter('platiGame', $platiGame)
            ->addOrderBy('p.date', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
