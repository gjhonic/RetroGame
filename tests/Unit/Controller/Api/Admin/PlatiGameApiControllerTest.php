<?php

namespace App\Tests\Unit\Controller\Api\Admin;

use App\Controller\Api\Admin\PlatiGameApiController;
use App\Entity\Game;
use App\Entity\PlatiGame;
use App\Repository\PlatiGameRepository;
use App\Service\PlatiGame\PlatiGameMapper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Мок PlatiGameRepository здесь и как стаб (готовые ответы findForAdminList/countForAdminList/find),
 * и как мок (проверка аргументов фильтров/сортировки/пагинации) — тот же паттерн, что в
 * SteamGameApiControllerTest.
 */
#[AllowMockObjectsWithoutExpectations]
class PlatiGameApiControllerTest extends TestCase
{
    private PlatiGameRepository&MockObject $platiGameRepository;
    private PlatiGameMapper $platiGameMapper;
    private PlatiGameApiController $controller;

    protected function setUp(): void
    {
        $this->platiGameRepository = $this->createMock(PlatiGameRepository::class);
        $this->platiGameMapper = new PlatiGameMapper();

        $this->controller = new PlatiGameApiController();
        // AbstractController::json() проверяет container->has('serializer') — пустой
        // контейнер без сервисов заставляет его отдать обычный JsonResponse.
        $this->controller->setContainer(new Container());
    }

    public function testListReturnsPageWithDefaultSortingAndPagination(): void
    {
        $game = (new Game('Half-Life', 'half-life'))->setCoverImagePath('uploads/games/1.jpg');
        (new \ReflectionProperty($game, 'id'))->setValue($game, 5);
        $platiGame = new PlatiGame($game, 'https://plati.market/itm/half-life', 'DarkAwe');

        $this->platiGameRepository->method('countForAdminList')->willReturn(1);
        $this->platiGameRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'createdAt', 'DESC', 25, 0)
            ->willReturn([$platiGame]);

        $response = $this->controller->list(new Request(), $this->platiGameRepository, $this->platiGameMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['total']);
        self::assertSame(1, $data['page']);
        self::assertSame(1, $data['totalPages']);
        self::assertSame([
            'id' => null,
            'gameId' => 5,
            'gameName' => 'Half-Life',
            'gameCoverImageUrl' => '/uploads/games/1.jpg',
            'url' => 'https://plati.market/itm/half-life',
            'sellerName' => 'DarkAwe',
            'createdAt' => $platiGame->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $platiGame->getUpdatedAt()->format('Y-m-d H:i:s'),
        ], $data['items'][0]);
    }

    public function testListPassesFiltersAndSortingToRepository(): void
    {
        $this->platiGameRepository->method('countForAdminList')->willReturn(0);
        $this->platiGameRepository->expects($this->once())
            ->method('findForAdminList')
            ->with(['game' => 'half', 'url' => 'half-life'], 'game', 'ASC', 10, 0)
            ->willReturn([]);

        $request = new Request([
            'filters' => ['game' => ' half ', 'url' => ' half-life ', 'unknownField' => 'ignored'],
            'sortBy' => 'game',
            'sortDir' => 'asc',
            'perPage' => '10',
        ]);
        $this->controller->list($request, $this->platiGameRepository, $this->platiGameMapper);
    }

    public function testListFallsBackToCreatedAtSortingForUnknownSortByAndClampsPerPage(): void
    {
        $this->platiGameRepository->method('countForAdminList')->willReturn(0);
        $this->platiGameRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'createdAt', 'DESC', 100, 0)
            ->willReturn([]);

        $request = new Request(['sortBy' => 'unknownField', 'perPage' => '9999']);
        $this->controller->list($request, $this->platiGameRepository, $this->platiGameMapper);
    }

    public function testListClampsRequestedPageToTotalPages(): void
    {
        $this->platiGameRepository->method('countForAdminList')->willReturn(1);
        $this->platiGameRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'createdAt', 'DESC', 25, 0)
            ->willReturn([]);

        $request = new Request(['page' => '999']);
        $response = $this->controller->list($request, $this->platiGameRepository, $this->platiGameMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['page']);
        self::assertSame(1, $data['totalPages']);
    }

    public function testShowReturnsFullDetailWithGameLink(): void
    {
        $game = (new Game('Half-Life', 'half-life'))->setCoverImagePath('uploads/games/1.jpg');
        (new \ReflectionProperty($game, 'id'))->setValue($game, 5);
        $platiGame = new PlatiGame($game, 'https://plati.market/itm/half-life', 'DarkAwe');

        $this->platiGameRepository->expects($this->once())
            ->method('find')
            ->with(42)
            ->willReturn($platiGame);

        $response = $this->controller->show(42, $this->platiGameRepository, $this->platiGameMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame('Half-Life', $data['gameName']);
        self::assertSame('half-life', $data['gameSlug']);
        self::assertSame('/uploads/games/1.jpg', $data['gameCoverImageUrl']);
        self::assertSame('https://plati.market/itm/half-life', $data['url']);
        self::assertSame('DarkAwe', $data['sellerName']);
    }

    public function testShowThrowsNotFoundExceptionForUnknownId(): void
    {
        $this->platiGameRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->show(999, $this->platiGameRepository, $this->platiGameMapper);
    }
}
