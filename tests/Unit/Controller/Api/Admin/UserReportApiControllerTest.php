<?php

namespace App\Tests\Unit\Controller\Api\Admin;

use App\Controller\Api\Admin\UserReportApiController;
use App\Entity\Enum\UserReportType;
use App\Entity\UserReport;
use App\Repository\UserReportRepository;
use App\Service\UserReport\UserReportMapper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

/**
 * Мок UserReportRepository здесь и как стаб (готовые ответы), и как мок
 * (проверка вызова) — строгая проверка "мок без expects()" отключена, как и
 * в AuditLogApiControllerTest.
 */
#[AllowMockObjectsWithoutExpectations]
class UserReportApiControllerTest extends TestCase
{
    private UserReportRepository&MockObject $userReportRepository;
    private UserReportApiController $controller;

    protected function setUp(): void
    {
        $this->userReportRepository = $this->createMock(UserReportRepository::class);

        $this->controller = new UserReportApiController();
        $this->controller->setContainer(new Container());
    }

    public function testListReturnsItemsWithDefaultPaginationAndSort(): void
    {
        $report = new UserReport(UserReportType::DieAgain, 'Игра вылетает на 3 уровне.');

        $this->userReportRepository->method('countForAdminList')->willReturn(1);
        $this->userReportRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'createdAt', 'DESC', 25, 0)
            ->willReturn([$report]);

        $response = $this->controller->list(new Request(), $this->userReportRepository, new UserReportMapper());
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['total']);
        self::assertSame(3, $data['items'][0]['type']);
        self::assertSame('Игра вылетает на 3 уровне.', $data['items'][0]['comment']);
    }

    public function testListPassesTypeFilterAndSortToRepository(): void
    {
        $this->userReportRepository->method('countForAdminList')->willReturn(0);
        $this->userReportRepository->expects($this->once())
            ->method('findForAdminList')
            ->with(['type' => '2'], 'type', 'ASC', 25, 0)
            ->willReturn([]);

        $request = new Request([
            'filters' => ['type' => '2'],
            'sortBy' => 'type',
            'sortDir' => 'asc',
        ]);
        $this->controller->list($request, $this->userReportRepository, new UserReportMapper());
    }

    public function testListClampsRequestedPageToTotalPages(): void
    {
        $this->userReportRepository->method('countForAdminList')->willReturn(1);
        $this->userReportRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'createdAt', 'DESC', 25, 0)
            ->willReturn([]);

        $response = $this->controller->list(
            new Request(['page' => '99']),
            $this->userReportRepository,
            new UserReportMapper(),
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['page']);
    }
}
