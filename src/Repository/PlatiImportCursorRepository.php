<?php

namespace App\Repository;

use App\Entity\PlatiImportCursor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlatiImportCursor>
 */
class PlatiImportCursorRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности PlatiImportCursor. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlatiImportCursor::class);
    }

    /** Возвращает единственную запись курсора, создавая её при первом обращении. */
    public function getOrCreate(): PlatiImportCursor
    {
        $cursor = $this->findOneBy([]);

        if ($cursor !== null) {
            return $cursor;
        }

        $cursor = new PlatiImportCursor();
        $this->getEntityManager()->persist($cursor);

        return $cursor;
    }
}
