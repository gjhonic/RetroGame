<?php

namespace App\Service\Cron;

use App\Cron\CronDiscoveryService;
use App\Entity\Cron;
use App\Repository\CronRepository;
use Doctrine\ORM\EntityManagerInterface;

/** Синхронизирует справочник кронов (App\Entity\Cron) со списком, обнаруженным по #[AsTrackedCron]. */
class CronSyncService
{
    public function __construct(
        private readonly CronDiscoveryService $cronDiscoveryService,
        private readonly CronRepository $cronRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** Создаёт записи для команд, ещё не встречавшихся в справочнике. Существующие записи не трогает. */
    public function sync(): void
    {
        $existingCommands = array_map(
            static fn (Cron $cron) => $cron->getCommand(),
            $this->cronRepository->findAllOrderedByCommand(),
        );

        $hasNew = false;
        foreach ($this->cronDiscoveryService->discoverTrackedCommandNames() as $commandName) {
            if (\in_array($commandName, $existingCommands, true)) {
                continue;
            }

            $this->entityManager->persist(new Cron($commandName));
            $hasNew = true;
        }

        if ($hasNew) {
            $this->entityManager->flush();
        }
    }
}
