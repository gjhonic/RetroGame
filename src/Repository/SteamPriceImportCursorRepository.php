<?php

namespace App\Repository;

use App\Entity\SteamPriceImportCursor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SteamPriceImportCursor>
 */
class SteamPriceImportCursorRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности SteamPriceImportCursor. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SteamPriceImportCursor::class);
    }

    /** Возвращает единственную запись курсора, создавая её при первом обращении. */
    public function getOrCreate(): SteamPriceImportCursor
    {
        $cursor = $this->findOneBy([]);

        if ($cursor !== null) {
            return $cursor;
        }

        $cursor = new SteamPriceImportCursor();
        $this->getEntityManager()->persist($cursor);

        return $cursor;
    }
}
