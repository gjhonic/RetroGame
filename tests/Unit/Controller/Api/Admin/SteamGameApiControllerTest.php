<?php

namespace App\Tests\Unit\Controller\Api\Admin;

use App\Controller\Api\Admin\SteamGameApiController;
use App\Entity\Enum\SteamGameStatus;
use App\Entity\Game;
use App\Entity\SteamGame;
use App\Repository\SteamGameRepository;
use App\Service\SteamGame\SteamGameMapper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Мок SteamGameRepository здесь и как стаб (готовые ответы findForAdminList/countForAdminList/find),
 * и как мок (проверка аргументов фильтров/сортировки/пагинации) — тот же паттерн, что в
 * GameApiControllerTest.
 */
#[AllowMockObjectsWithoutExpectations]
class SteamGameApiControllerTest extends TestCase
{
    private SteamGameRepository&MockObject $steamGameRepository;
    private SteamGameMapper $steamGameMapper;
    private SteamGameApiController $controller;

    protected function setUp(): void
    {
        $this->steamGameRepository = $this->createMock(SteamGameRepository::class);
        $this->steamGameMapper = new SteamGameMapper();

        $this->controller = new SteamGameApiController();
        // AbstractController::json() проверяет container->has('serializer') — пустой
        // контейнер без сервисов заставляет его отдать обычный JsonResponse.
        $this->controller->setContainer(new Container());
    }

    public function testListReturnsPageWithDefaultSortingAndPagination(): void
    {
        $game = (new Game('Half-Life', 'half-life'))->setCoverImagePath('uploads/games/1.jpg');
        $steamGame = (new SteamGame(70))->setGame($game)->markSuccess(['type' => 'game']);

        $this->steamGameRepository->method('countForAdminList')->willReturn(1);
        $this->steamGameRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'steamAppId', 'ASC', 25, 0)
            ->willReturn([$steamGame]);

        $response = $this->controller->list(new Request(), $this->steamGameRepository, $this->steamGameMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['total']);
        self::assertSame(1, $data['page']);
        self::assertSame(1, $data['totalPages']);
        self::assertSame([
            'id' => null,
            'steamAppId' => 70,
            'status' => 'success',
            'gameId' => null,
            'gameName' => 'Half-Life',
            'gameCoverImageUrl' => '/uploads/games/1.jpg',
            'attempts' => 1,
            'fetchedAt' => $steamGame->getFetchedAt()?->format('Y-m-d H:i:s'),
            'lastAttemptAt' => $steamGame->getLastAttemptAt()?->format('Y-m-d H:i:s'),
        ], $data['items'][0]);
    }

    public function testListPassesFiltersAndSortingToRepository(): void
    {
        $this->steamGameRepository->method('countForAdminList')->willReturn(0);
        $this->steamGameRepository->expects($this->once())
            ->method('findForAdminList')
            ->with(['steamAppId' => '70', 'game' => 'half'], 'status', 'DESC', 10, 0)
            ->willReturn([]);

        $request = new Request([
            'filters' => ['steamAppId' => ' 70 ', 'game' => ' half ', 'unknownField' => 'ignored'],
            'sortBy' => 'status',
            'sortDir' => 'desc',
            'perPage' => '10',
        ]);
        $this->controller->list($request, $this->steamGameRepository, $this->steamGameMapper);
    }

    public function testListFallsBackToSteamAppIdSortingForUnknownSortByAndClampsPerPage(): void
    {
        $this->steamGameRepository->method('countForAdminList')->willReturn(0);
        $this->steamGameRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'steamAppId', 'ASC', 100, 0)
            ->willReturn([]);

        $request = new Request(['sortBy' => 'unknownField', 'perPage' => '9999']);
        $this->controller->list($request, $this->steamGameRepository, $this->steamGameMapper);
    }

    public function testListClampsRequestedPageToTotalPages(): void
    {
        $this->steamGameRepository->method('countForAdminList')->willReturn(1);
        $this->steamGameRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'steamAppId', 'ASC', 25, 0)
            ->willReturn([]);

        $request = new Request(['page' => '999']);
        $response = $this->controller->list($request, $this->steamGameRepository, $this->steamGameMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['page']);
        self::assertSame(1, $data['totalPages']);
    }

    public function testShowReturnsFullDetailWithGameLinkAndRawData(): void
    {
        $game = (new Game('Half-Life', 'half-life'))->setCoverImagePath('uploads/games/1.jpg');
        $steamGame = (new SteamGame(70))->setGame($game)->markSuccess(['type' => 'game', 'name' => 'Half-Life']);

        $this->steamGameRepository->expects($this->once())
            ->method('find')
            ->with(42)
            ->willReturn($steamGame);

        $response = $this->controller->show(42, $this->steamGameRepository, $this->steamGameMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(70, $data['steamAppId']);
        self::assertSame(SteamGameStatus::Success->value, $data['status']);
        self::assertSame('Half-Life', $data['gameName']);
        self::assertSame('/uploads/games/1.jpg', $data['gameCoverImageUrl']);
        self::assertSame(['type' => 'game', 'name' => 'Half-Life'], $data['rawData']);
    }

    public function testShowThrowsNotFoundExceptionForUnknownId(): void
    {
        $this->steamGameRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->show(999, $this->steamGameRepository, $this->steamGameMapper);
    }
}
