<?php

namespace App\Repository;

use App\Entity\GgselImportCursor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GgselImportCursor>
 */
class GgselImportCursorRepository extends ServiceEntityRepository
{
    /** Регистрирует репозиторий для сущности GgselImportCursor. */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GgselImportCursor::class);
    }

    /** Возвращает единственную запись курсора, создавая её при первом обращении. */
    public function getOrCreate(): GgselImportCursor
    {
        $cursor = $this->findOneBy([]);

        if ($cursor !== null) {
            return $cursor;
        }

        $cursor = new GgselImportCursor();
        $this->getEntityManager()->persist($cursor);

        return $cursor;
    }
}
