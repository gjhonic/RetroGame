<?php

namespace App\Repository;

use App\Entity\SteamImportCursor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SteamImportCursor>
 */
class SteamImportCursorRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности SteamImportCursor. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SteamImportCursor::class);
    }

    /** Возвращает единственную запись курсора, создавая её при первом обращении. */
    public function getOrCreate(): SteamImportCursor
    {
        $cursor = $this->findOneBy([]);

        if ($cursor !== null) {
            return $cursor;
        }

        $cursor = new SteamImportCursor();
        $this->getEntityManager()->persist($cursor);

        return $cursor;
    }
}
