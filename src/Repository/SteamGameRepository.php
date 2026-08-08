<?php

namespace App\Repository;

use App\Entity\SteamGame;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SteamGame>
 */
class SteamGameRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности SteamGame. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SteamGame::class);
    }

    /** Ищет запись по appid игры в Steam. */
    public function findOneBySteamAppId(int $steamAppId): ?SteamGame
    {
        return $this->findOneBy(['steamAppId' => $steamAppId]);
    }
}
