<?php

namespace App\Tests\Unit\Controller\Api\Admin;

use App\Controller\Api\Admin\GgselGameApiController;
use App\Entity\Game;
use App\Entity\GgselGame;
use App\Repository\GgselGameRepository;
use App\Service\GgselGame\GgselGameMapper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Мок GgselGameRepository здесь и как стаб (готовые ответы findForAdminList/countForAdminList/find),
 * и как мок (проверка аргументов фильтров/сортировки/пагинации) — тот же паттерн, что в
 * SteamGameApiControllerTest.
 */
#[AllowMockObjectsWithoutExpectations]
class GgselGameApiControllerTest extends TestCase
{
    private GgselGameRepository&MockObject $ggselGameRepository;
    private GgselGameMapper $ggselGameMapper;
    private GgselGameApiController $controller;

    protected function setUp(): void
    {
        $this->ggselGameRepository = $this->createMock(GgselGameRepository::class);
        $this->ggselGameMapper = new GgselGameMapper();

        $this->controller = new GgselGameApiController();
        // AbstractController::json() проверяет container->has('serializer') — пустой
        // контейнер без сервисов заставляет его отдать обычный JsonResponse.
        $this->controller->setContainer(new Container());
    }

    public function testListReturnsPageWithDefaultSortingAndPagination(): void
    {
        $game = (new Game('Half-Life', 'half-life'))->setCoverImagePath('uploads/games/1.jpg');
        (new \ReflectionProperty($game, 'id'))->setValue($game, 5);
        $ggselGame = new GgselGame($game, 'https://ggsel.net/catalog/half-life');

        $this->ggselGameRepository->method('countForAdminList')->willReturn(1);
        $this->ggselGameRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'createdAt', 'DESC', 25, 0)
            ->willReturn([$ggselGame]);

        $response = $this->controller->list(new Request(), $this->ggselGameRepository, $this->ggselGameMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['total']);
        self::assertSame(1, $data['page']);
        self::assertSame(1, $data['totalPages']);
        self::assertSame([
            'id' => null,
            'gameId' => 5,
            'gameName' => 'Half-Life',
            'gameCoverImageUrl' => '/uploads/games/1.jpg',
            'url' => 'https://ggsel.net/catalog/half-life',
            'createdAt' => $ggselGame->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $ggselGame->getUpdatedAt()->format('Y-m-d H:i:s'),
        ], $data['items'][0]);
    }

    public function testListPassesFiltersAndSortingToRepository(): void
    {
        $this->ggselGameRepository->method('countForAdminList')->willReturn(0);
        $this->ggselGameRepository->expects($this->once())
            ->method('findForAdminList')
            ->with(['game' => 'half', 'url' => 'half-life'], 'game', 'ASC', 10, 0)
            ->willReturn([]);

        $request = new Request([
            'filters' => ['game' => ' half ', 'url' => ' half-life ', 'unknownField' => 'ignored'],
            'sortBy' => 'game',
            'sortDir' => 'asc',
            'perPage' => '10',
        ]);
        $this->controller->list($request, $this->ggselGameRepository, $this->ggselGameMapper);
    }

    public function testListFallsBackToCreatedAtSortingForUnknownSortByAndClampsPerPage(): void
    {
        $this->ggselGameRepository->method('countForAdminList')->willReturn(0);
        $this->ggselGameRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'createdAt', 'DESC', 100, 0)
            ->willReturn([]);

        $request = new Request(['sortBy' => 'unknownField', 'perPage' => '9999']);
        $this->controller->list($request, $this->ggselGameRepository, $this->ggselGameMapper);
    }

    public function testListClampsRequestedPageToTotalPages(): void
    {
        $this->ggselGameRepository->method('countForAdminList')->willReturn(1);
        $this->ggselGameRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'createdAt', 'DESC', 25, 0)
            ->willReturn([]);

        $request = new Request(['page' => '999']);
        $response = $this->controller->list($request, $this->ggselGameRepository, $this->ggselGameMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['page']);
        self::assertSame(1, $data['totalPages']);
    }

    public function testShowReturnsFullDetailWithGameLink(): void
    {
        $game = (new Game('Half-Life', 'half-life'))->setCoverImagePath('uploads/games/1.jpg');
        (new \ReflectionProperty($game, 'id'))->setValue($game, 5);
        $ggselGame = new GgselGame($game, 'https://ggsel.net/catalog/half-life');

        $this->ggselGameRepository->expects($this->once())
            ->method('find')
            ->with(42)
            ->willReturn($ggselGame);

        $response = $this->controller->show(42, $this->ggselGameRepository, $this->ggselGameMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame('Half-Life', $data['gameName']);
        self::assertSame('half-life', $data['gameSlug']);
        self::assertSame('/uploads/games/1.jpg', $data['gameCoverImageUrl']);
        self::assertSame('https://ggsel.net/catalog/half-life', $data['url']);
    }

    public function testShowThrowsNotFoundExceptionForUnknownId(): void
    {
        $this->ggselGameRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->show(999, $this->ggselGameRepository, $this->ggselGameMapper);
    }
}
