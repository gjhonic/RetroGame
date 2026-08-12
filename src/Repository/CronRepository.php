<?php

namespace App\Repository;

use App\Entity\Cron;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cron>
 */
class CronRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cron::class);
    }

    public function findOneByCommand(string $command): ?Cron
    {
        return $this->findOneBy(['command' => $command]);
    }

    /** @return list<Cron> */
    public function findAllOrderedByCommand(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.command', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Все кроны в виде словаря command => Cron — используется, чтобы подмешать
     * настроенные пользователем name/color к записям CronRun (связи в БД нет,
     * см. App\Entity\CronRun) без запроса на каждую строку истории запусков.
     *
     * @return array<string, Cron>
     */
    public function findAllIndexedByCommand(): array
    {
        $indexed = [];
        foreach ($this->findAllOrderedByCommand() as $cron) {
            $indexed[$cron->getCommand()] = $cron;
        }

        return $indexed;
    }
}
