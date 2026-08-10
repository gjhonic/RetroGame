<?php

namespace App\Repository;

use App\Entity\Dlc;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Dlc>
 */
class DlcRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности Dlc. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dlc::class);
    }

    /**
     * DLC, ожидающие импорта указанной базовой игры (game ещё не привязан) —
     * используется для доотвязки, когда эта игра наконец импортируется.
     *
     * @return array<int, Dlc>
     */
    public function findPendingBySteamAppId(int $steamAppId): array
    {
        return $this->findBy(['game' => null, 'pendingBaseGameSteamAppId' => $steamAppId]);
    }
}
