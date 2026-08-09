<?php

namespace App\Tests\Unit\Controller\Api\Admin;

use App\Controller\Api\Admin\DeveloperApiController;
use App\Repository\DeveloperRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

/**
 * Мок DeveloperRepository здесь и как стаб (готовые ответы countForAdminList),
 * и как мок (проверка аргументов фильтров/сортировки/пагинации) — строгая
 * проверка "мок без expects()" отключена, как в GameApiControllerTest.
 */
#[AllowMockObjectsWithoutExpectations]
class DeveloperApiControllerTest extends TestCase
{
    private DeveloperRepository&MockObject $developerRepository;
    private DeveloperApiController $controller;

    protected function setUp(): void
    {
        $this->developerRepository = $this->createMock(DeveloperRepository::class);

        $this->controller = new DeveloperApiController();
        $this->controller->setContainer(new Container());
    }

    public function testListReturnsPageWithDefaultSortingAndPagination(): void
    {
        $this->developerRepository->method('countForAdminList')->willReturn(1);
        $this->developerRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'name', 'ASC', 25, 0)
            ->willReturn([['id' => 1, 'name' => 'Valve', 'gamesCount' => 12]]);

        $response = $this->controller->list(new Request(), $this->developerRepository);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['total']);
        self::assertSame(1, $data['page']);
        self::assertSame(1, $data['totalPages']);
        self::assertSame(['id' => 1, 'name' => 'Valve', 'gamesCount' => 12], $data['items'][0]);
    }

    public function testListPassesFiltersAndSortingToRepository(): void
    {
        $this->developerRepository->method('countForAdminList')->willReturn(0);
        $this->developerRepository->expects($this->once())
            ->method('findForAdminList')
            ->with(['name' => 'valve'], 'gamesCount', 'DESC', 10, 0)
            ->willReturn([]);

        $request = new Request([
            'filters' => ['name' => ' valve ', 'unknownField' => 'ignored'],
            'sortBy' => 'gamesCount',
            'sortDir' => 'desc',
            'perPage' => '10',
        ]);
        $this->controller->list($request, $this->developerRepository);
    }

    public function testListFallsBackToNameSortingForUnknownSortByAndClampsPerPage(): void
    {
        $this->developerRepository->method('countForAdminList')->willReturn(0);
        $this->developerRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'name', 'ASC', 100, 0)
            ->willReturn([]);

        $request = new Request(['sortBy' => 'unknownField', 'perPage' => '9999']);
        $this->controller->list($request, $this->developerRepository);
    }

    public function testListClampsRequestedPageToTotalPages(): void
    {
        $this->developerRepository->method('countForAdminList')->willReturn(1);
        $this->developerRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'name', 'ASC', 25, 0)
            ->willReturn([]);

        $response = $this->controller->list(new Request(['page' => '999']), $this->developerRepository);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['page']);
        self::assertSame(1, $data['totalPages']);
    }
}
