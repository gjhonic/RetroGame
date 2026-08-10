<?php

namespace App\Tests\Unit\Service\Cron;

use App\Cron\CronDiscoveryService;
use App\Entity\Cron;
use App\Repository\CronRepository;
use App\Service\Cron\CronSyncService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Мок CronRepository здесь и как стаб (готовый ответ findAllOrderedByCommand),
 * и как мок (проверка отсутствия лишних вызовов) — строгая проверка "мок без
 * expects()" отключена, как в GameApiControllerTest.
 */
#[AllowMockObjectsWithoutExpectations]
class CronSyncServiceTest extends TestCase
{
    private CronDiscoveryService&MockObject $cronDiscoveryService;
    private CronRepository&MockObject $cronRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private CronSyncService $service;

    protected function setUp(): void
    {
        $this->cronDiscoveryService = $this->createMock(CronDiscoveryService::class);
        $this->cronRepository = $this->createMock(CronRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->service = new CronSyncService($this->cronDiscoveryService, $this->cronRepository, $this->entityManager);
    }

    public function testSyncPersistsOnlyCommandsMissingFromRepository(): void
    {
        $this->cronDiscoveryService->method('discoverTrackedCommandNames')
            ->willReturn(['app:games:import', 'app:already:known']);
        $this->cronRepository->method('findAllOrderedByCommand')
            ->willReturn([new Cron('app:already:known')]);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(static fn (Cron $cron) => $cron->getCommand() === 'app:games:import'));
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->sync();
    }

    public function testSyncDoesNothingWhenAllCommandsAlreadyKnown(): void
    {
        $this->cronDiscoveryService->method('discoverTrackedCommandNames')->willReturn(['app:games:import']);
        $this->cronRepository->method('findAllOrderedByCommand')->willReturn([new Cron('app:games:import')]);

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $this->service->sync();
    }
}
