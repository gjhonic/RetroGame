<?php

namespace App\Repository;

use App\Entity\PlatiPriceImportCursor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlatiPriceImportCursor>
 */
class PlatiPriceImportCursorRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности PlatiPriceImportCursor. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlatiPriceImportCursor::class);
    }

    /** Возвращает единственную запись курсора, создавая её при первом обращении. */
    public function getOrCreate(): PlatiPriceImportCursor
    {
        $cursor = $this->findOneBy([]);

        if ($cursor !== null) {
            return $cursor;
        }

        $cursor = new PlatiPriceImportCursor();
        $this->getEntityManager()->persist($cursor);

        return $cursor;
    }
}
