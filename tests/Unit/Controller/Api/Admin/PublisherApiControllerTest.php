<?php

namespace App\Tests\Unit\Controller\Api\Admin;

use App\Controller\Api\Admin\PublisherApiController;
use App\Repository\PublisherRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

/**
 * Мок PublisherRepository здесь и как стаб (готовые ответы countForAdminList),
 * и как мок (проверка аргументов фильтров/сортировки/пагинации) — строгая
 * проверка "мок без expects()" отключена, как в GameApiControllerTest.
 */
#[AllowMockObjectsWithoutExpectations]
class PublisherApiControllerTest extends TestCase
{
    private PublisherRepository&MockObject $publisherRepository;
    private PublisherApiController $controller;

    protected function setUp(): void
    {
        $this->publisherRepository = $this->createMock(PublisherRepository::class);

        $this->controller = new PublisherApiController();
        $this->controller->setContainer(new Container());
    }

    public function testListReturnsPageWithDefaultSortingAndPagination(): void
    {
        $this->publisherRepository->method('countForAdminList')->willReturn(1);
        $this->publisherRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'name', 'ASC', 25, 0)
            ->willReturn([['id' => 1, 'name' => 'Sierra', 'gamesCount' => 7]]);

        $response = $this->controller->list(new Request(), $this->publisherRepository);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['total']);
        self::assertSame(1, $data['page']);
        self::assertSame(1, $data['totalPages']);
        self::assertSame(['id' => 1, 'name' => 'Sierra', 'gamesCount' => 7], $data['items'][0]);
    }

    public function testListPassesFiltersAndSortingToRepository(): void
    {
        $this->publisherRepository->method('countForAdminList')->willReturn(0);
        $this->publisherRepository->expects($this->once())
            ->method('findForAdminList')
            ->with(['name' => 'sierra'], 'gamesCount', 'DESC', 10, 0)
            ->willReturn([]);

        $request = new Request([
            'filters' => ['name' => ' sierra ', 'unknownField' => 'ignored'],
            'sortBy' => 'gamesCount',
            'sortDir' => 'desc',
            'perPage' => '10',
        ]);
        $this->controller->list($request, $this->publisherRepository);
    }

    public function testListFallsBackToNameSortingForUnknownSortByAndClampsPerPage(): void
    {
        $this->publisherRepository->method('countForAdminList')->willReturn(0);
        $this->publisherRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'name', 'ASC', 100, 0)
            ->willReturn([]);

        $request = new Request(['sortBy' => 'unknownField', 'perPage' => '9999']);
        $this->controller->list($request, $this->publisherRepository);
    }

    public function testListClampsRequestedPageToTotalPages(): void
    {
        $this->publisherRepository->method('countForAdminList')->willReturn(1);
        $this->publisherRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'name', 'ASC', 25, 0)
            ->willReturn([]);

        $response = $this->controller->list(new Request(['page' => '999']), $this->publisherRepository);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['page']);
        self::assertSame(1, $data['totalPages']);
    }
}
