<?php

namespace App\Tests\Unit\Controller\Api\Admin;

use App\Controller\Api\Admin\GenreApiController;
use App\Repository\GenreRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

/**
 * Мок GenreRepository здесь и как стаб (готовые ответы countForAdminList),
 * и как мок (проверка аргументов фильтров/сортировки/пагинации) — строгая
 * проверка "мок без expects()" отключена, как в GameApiControllerTest.
 */
#[AllowMockObjectsWithoutExpectations]
class GenreApiControllerTest extends TestCase
{
    private GenreRepository&MockObject $genreRepository;
    private GenreApiController $controller;

    protected function setUp(): void
    {
        $this->genreRepository = $this->createMock(GenreRepository::class);

        $this->controller = new GenreApiController();
        $this->controller->setContainer(new Container());
    }

    public function testListReturnsPageWithDefaultSortingAndPagination(): void
    {
        $this->genreRepository->method('countForAdminList')->willReturn(1);
        $this->genreRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'name', 'ASC', 25, 0)
            ->willReturn([['id' => 1, 'name' => 'Экшены', 'gamesCount' => 42]]);

        $response = $this->controller->list(new Request(), $this->genreRepository);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['total']);
        self::assertSame(1, $data['page']);
        self::assertSame(1, $data['totalPages']);
        self::assertSame(['id' => 1, 'name' => 'Экшены', 'gamesCount' => 42], $data['items'][0]);
    }

    public function testListPassesFiltersAndSortingToRepository(): void
    {
        $this->genreRepository->method('countForAdminList')->willReturn(0);
        $this->genreRepository->expects($this->once())
            ->method('findForAdminList')
            ->with(['name' => 'action'], 'gamesCount', 'DESC', 10, 0)
            ->willReturn([]);

        $request = new Request([
            'filters' => ['name' => ' action ', 'unknownField' => 'ignored'],
            'sortBy' => 'gamesCount',
            'sortDir' => 'desc',
            'perPage' => '10',
        ]);
        $this->controller->list($request, $this->genreRepository);
    }

    public function testListFallsBackToNameSortingForUnknownSortByAndClampsPerPage(): void
    {
        $this->genreRepository->method('countForAdminList')->willReturn(0);
        $this->genreRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'name', 'ASC', 100, 0)
            ->willReturn([]);

        $request = new Request(['sortBy' => 'unknownField', 'perPage' => '9999']);
        $this->controller->list($request, $this->genreRepository);
    }

    public function testListClampsRequestedPageToTotalPages(): void
    {
        $this->genreRepository->method('countForAdminList')->willReturn(1);
        $this->genreRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'name', 'ASC', 25, 0)
            ->willReturn([]);

        $response = $this->controller->list(new Request(['page' => '999']), $this->genreRepository);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['page']);
        self::assertSame(1, $data['totalPages']);
    }
}
