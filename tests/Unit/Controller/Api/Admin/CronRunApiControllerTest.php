<?php

namespace App\Tests\Unit\Controller\Api\Admin;

use App\Controller\Api\Admin\CronRunApiController;
use App\Entity\Cron;
use App\Entity\CronRun;
use App\Entity\Enum\CronRunStatus;
use App\Repository\CronRepository;
use App\Repository\CronRunRepository;
use App\Service\Cron\CronLogReader;
use App\Service\Cron\CronRunMapper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Мок CronRunRepository здесь и как стаб (готовые ответы findForAdminList/countForAdminList),
 * и как мок (проверка аргументов фильтров/сортировки/пагинации) — строгая
 * проверка "мок без expects()" отключена, как в GameApiControllerTest.
 */
#[AllowMockObjectsWithoutExpectations]
class CronRunApiControllerTest extends TestCase
{
    private CronRunRepository&MockObject $cronRunRepository;
    private CronRepository&MockObject $cronRepository;
    private CronRunApiController $controller;
    private CronRunMapper $mapper;

    protected function setUp(): void
    {
        $this->cronRunRepository = $this->createMock(CronRunRepository::class);
        $this->cronRepository = $this->createMock(CronRepository::class);
        $this->cronRepository->method('findAllIndexedByCommand')->willReturn([]);
        $this->mapper = new CronRunMapper();

        $this->controller = new CronRunApiController();
        $this->controller->setContainer(new Container());
    }

    public function testListReturnsPageWithDefaultSortingAndPagination(): void
    {
        $run = new CronRun('app:games:import', '--limit=1', 'app_games_import/1.log');

        $this->cronRunRepository->method('countForAdminList')->willReturn(1);
        $this->cronRunRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], null, null, 'startedAt', 'DESC', 25, 0)
            ->willReturn([$run]);

        $request = new Request();
        $response = $this->controller->list($request, $this->cronRunRepository, $this->cronRepository, $this->mapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['total']);
        self::assertSame(1, $data['page']);
        self::assertSame(1, $data['totalPages']);
        self::assertSame('app:games:import', $data['items'][0]['command']);
        self::assertSame('running', $data['items'][0]['status']);
    }

    public function testListIncludesMatchingCronNameAndColor(): void
    {
        $run = new CronRun('app:games:import', null, null);
        $cron = (new Cron('app:games:import'))->setName('Импорт игр из Steam')->setColor('#198754');

        $this->cronRunRepository->method('countForAdminList')->willReturn(1);
        $this->cronRunRepository->method('findForAdminList')->willReturn([$run]);
        $this->cronRepository = $this->createMock(CronRepository::class);
        $this->cronRepository->method('findAllIndexedByCommand')->willReturn(['app:games:import' => $cron]);

        $request = new Request();
        $response = $this->controller->list($request, $this->cronRunRepository, $this->cronRepository, $this->mapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame('Импорт игр из Steam', $data['items'][0]['cronName']);
        self::assertSame('#198754', $data['items'][0]['cronColor']);
    }

    public function testListPassesFiltersAndSortingToRepository(): void
    {
        $this->cronRunRepository->method('countForAdminList')->willReturn(0);
        $this->cronRunRepository->expects($this->once())
            ->method('findForAdminList')
            ->with(['command' => 'app:games:import', 'status' => 'failed'], null, null, 'durationMs', 'ASC', 10, 0)
            ->willReturn([]);

        $request = new Request([
            'filters' => ['command' => ' app:games:import ', 'status' => ' failed '],
            'sortBy' => 'durationMs',
            'sortDir' => 'asc',
            'perPage' => '10',
        ]);
        $this->controller->list($request, $this->cronRunRepository, $this->cronRepository, $this->mapper);
    }

    public function testListPassesDateRangeToRepository(): void
    {
        $expectedFrom = new \DateTimeImmutable('2026-08-01T00:00');
        $expectedTo = new \DateTimeImmutable('2026-08-02T00:00');

        $this->cronRunRepository->method('countForAdminList')->willReturn(0);
        $this->cronRunRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], $expectedFrom, $expectedTo, 'startedAt', 'DESC', 25, 0)
            ->willReturn([]);

        $request = new Request(['dateFrom' => '2026-08-01T00:00', 'dateTo' => '2026-08-02T00:00']);
        $this->controller->list($request, $this->cronRunRepository, $this->cronRepository, $this->mapper);
    }

    public function testListFallsBackToStartedAtSortingForUnknownSortBy(): void
    {
        $this->cronRunRepository->method('countForAdminList')->willReturn(0);
        $this->cronRunRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], null, null, 'startedAt', 'DESC', 25, 0)
            ->willReturn([]);

        $request = new Request(['sortBy' => 'unknownField']);
        $this->controller->list($request, $this->cronRunRepository, $this->cronRepository, $this->mapper);
    }

    public function testShowReturnsRunDetail(): void
    {
        $run = new CronRun('app:games:import', '--limit=1', 'app_games_import/1.log');
        $run->finish(CronRunStatus::Success, 0, 1024, null);

        $this->cronRunRepository->expects($this->once())->method('find')->with(1)->willReturn($run);

        $response = $this->controller->show(1, $this->cronRunRepository, $this->cronRepository, $this->mapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame('success', $data['status']);
        self::assertSame('--limit=1', $data['arguments']);
        self::assertTrue($data['hasLog']);
    }

    public function testShowThrowsNotFoundExceptionForUnknownId(): void
    {
        $this->cronRunRepository->expects($this->once())->method('find')->with(999)->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->controller->show(999, $this->cronRunRepository, $this->cronRepository, $this->mapper);
    }

    public function testCommandsReturnsDistinctCommandList(): void
    {
        $this->cronRunRepository->method('findDistinctCommands')->willReturn(['app:games:import']);

        $response = $this->controller->commands($this->cronRunRepository);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(['app:games:import'], $data['commands']);
    }

    public function testTimelinePassesRequestedDateRangeToRepository(): void
    {
        $run = new CronRun('app:games:import', null, null);
        $expectedFrom = new \DateTimeImmutable('2026-08-01T00:00');
        $expectedTo = new \DateTimeImmutable('2026-08-02T00:00');

        $this->cronRunRepository->expects($this->once())
            ->method('findForTimeline')
            ->with($expectedFrom, $expectedTo)
            ->willReturn([$run]);

        $request = new Request(['dateFrom' => '2026-08-01T00:00', 'dateTo' => '2026-08-02T00:00']);
        $response = $this->controller->timeline(
            $request,
            $this->cronRunRepository,
            $this->cronRepository,
            $this->mapper,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertCount(1, $data['items']);
    }

    public function testTimelineDefaultsToLastDayWhenRangeNotProvided(): void
    {
        $this->cronRunRepository->expects($this->once())
            ->method('findForTimeline')
            ->with(self::isInstanceOf(\DateTimeImmutable::class), self::isInstanceOf(\DateTimeImmutable::class))
            ->willReturn([]);

        $this->controller->timeline(new Request(), $this->cronRunRepository, $this->cronRepository, $this->mapper);
    }

    public function testLogReturnsPlainTextContent(): void
    {
        $run = new CronRun('app:games:import', null, 'app_games_import/1.log');
        $this->cronRunRepository->expects($this->once())->method('find')->with(1)->willReturn($run);

        $logReader = $this->createMock(CronLogReader::class);
        $logReader->expects($this->once())->method('read')->with($run)->willReturn('строка лога');

        $response = $this->controller->log(1, new Request(), $this->cronRunRepository, $logReader);

        self::assertSame('строка лога', $response->getContent());
        self::assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));
        self::assertNull($response->headers->get('Content-Disposition'));
    }

    public function testLogWithDownloadFlagSetsContentDisposition(): void
    {
        $run = new CronRun('app:games:import', null, 'app_games_import/1.log');
        $this->cronRunRepository->expects($this->once())->method('find')->with(1)->willReturn($run);

        $logReader = $this->createMock(CronLogReader::class);
        $logReader->expects($this->once())->method('read')->with($run)->willReturn('строка лога');

        $request = new Request(['download' => '1']);
        $response = $this->controller->log(1, $request, $this->cronRunRepository, $logReader);

        $disposition = (string) $response->headers->get('Content-Disposition');
        self::assertStringContainsString('attachment', $disposition);
        self::assertStringContainsString('app_games_import-1.log', $disposition);
    }

    public function testLogThrowsNotFoundExceptionWhenLogFileMissing(): void
    {
        $run = new CronRun('app:games:import', null, null);
        $this->cronRunRepository->expects($this->once())->method('find')->with(1)->willReturn($run);

        $logReader = $this->createMock(CronLogReader::class);
        $logReader->expects($this->once())->method('read')->with($run)->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->controller->log(1, new Request(), $this->cronRunRepository, $logReader);
    }
}
