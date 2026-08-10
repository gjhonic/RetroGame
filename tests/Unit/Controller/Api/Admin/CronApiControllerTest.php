<?php

namespace App\Tests\Unit\Controller\Api\Admin;

use App\Controller\Api\Admin\CronApiController;
use App\Entity\Cron;
use App\Entity\CronRun;
use App\Repository\CronRepository;
use App\Repository\CronRunRepository;
use App\Service\Cron\CronMapper;
use App\Service\Cron\CronSyncService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Моки репозиториев здесь и как стабы (готовые ответы find/findAllOrderedByCommand),
 * и как моки (проверка вызова sync()/flush()) — строгая проверка "мок без
 * expects()" отключена, как в GameApiControllerTest.
 */
#[AllowMockObjectsWithoutExpectations]
class CronApiControllerTest extends TestCase
{
    private CronRepository&MockObject $cronRepository;
    private CronRunRepository&MockObject $cronRunRepository;
    private CronSyncService&MockObject $cronSyncService;
    private EntityManagerInterface&MockObject $entityManager;
    private CronMapper $mapper;
    private CronApiController $controller;

    protected function setUp(): void
    {
        $this->cronRepository = $this->createMock(CronRepository::class);
        $this->cronRunRepository = $this->createMock(CronRunRepository::class);
        $this->cronSyncService = $this->createMock(CronSyncService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->mapper = new CronMapper();

        $this->controller = new CronApiController();
        $this->controller->setContainer(new Container());
    }

    public function testListSyncsAndReturnsCronsWithLastRun(): void
    {
        $cron = new Cron('app:games:import');
        $run = new CronRun('app:games:import', null, null);

        $this->cronSyncService->expects($this->once())->method('sync');
        $this->cronRepository->method('findAllOrderedByCommand')->willReturn([$cron]);
        $this->cronRunRepository->expects($this->once())
            ->method('findLatest')
            ->with('app:games:import')
            ->willReturn($run);

        $response = $this->controller->list(
            $this->cronSyncService,
            $this->cronRepository,
            $this->cronRunRepository,
            $this->mapper,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame('app:games:import', $data['items'][0]['command']);
        self::assertSame('running', $data['items'][0]['lastRun']['status']);
    }

    public function testListReturnsNullLastRunWhenCronNeverRan(): void
    {
        $cron = new Cron('app:games:import');

        $this->cronRepository->method('findAllOrderedByCommand')->willReturn([$cron]);
        $this->cronRunRepository->method('findLatest')->willReturn(null);

        $response = $this->controller->list(
            $this->cronSyncService,
            $this->cronRepository,
            $this->cronRunRepository,
            $this->mapper,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertNull($data['items'][0]['lastRun']);
    }

    public function testShowReturnsCronDetail(): void
    {
        $cron = new Cron('app:games:import');
        $this->cronRepository->expects($this->once())->method('find')->with(1)->willReturn($cron);

        $response = $this->controller->show(1, $this->cronRepository, $this->mapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame('app:games:import', $data['command']);
    }

    public function testShowThrowsNotFoundExceptionForUnknownId(): void
    {
        $this->cronRepository->expects($this->once())->method('find')->with(999)->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->controller->show(999, $this->cronRepository, $this->mapper);
    }

    public function testUpdateColorSetsValidColorAndFlushes(): void
    {
        $cron = new Cron('app:games:import');
        $this->cronRepository->expects($this->once())->method('find')->with(1)->willReturn($cron);
        $this->entityManager->expects($this->once())->method('flush');

        $request = new Request(content: json_encode(['color' => '#198754'], \JSON_THROW_ON_ERROR));
        $response = $this->controller->updateColor(
            1,
            $request,
            $this->cronRepository,
            $this->mapper,
            $this->entityManager,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame('#198754', $data['color']);
        self::assertSame('#198754', $cron->getColor());
    }

    public function testUpdateColorRejectsInvalidFormat(): void
    {
        $cron = new Cron('app:games:import');
        $this->cronRepository->method('find')->willReturn($cron);
        $this->entityManager->expects($this->never())->method('flush');

        $request = new Request(content: json_encode(['color' => 'red'], \JSON_THROW_ON_ERROR));
        $response = $this->controller->updateColor(
            1,
            $request,
            $this->cronRepository,
            $this->mapper,
            $this->entityManager,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('color', $data['errors']);
    }

    public function testUpdateColorThrowsNotFoundExceptionForUnknownId(): void
    {
        $this->cronRepository->expects($this->once())->method('find')->with(999)->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $request = new Request(content: json_encode(['color' => '#198754'], \JSON_THROW_ON_ERROR));
        $this->controller->updateColor(999, $request, $this->cronRepository, $this->mapper, $this->entityManager);
    }
}
