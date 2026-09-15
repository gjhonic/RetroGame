<?php

namespace App\Tests\Unit\Controller\Api\Admin;

use App\Controller\Api\Admin\GameApiController;
use App\Entity\Developer;
use App\Entity\Game;
use App\Entity\Genre;
use App\Entity\GamePrice;
use App\Entity\Platform;
use App\Entity\Publisher;
use App\Entity\SteamGame;
use App\Repository\GamePriceRepository;
use App\Repository\GameRepository;
use App\Repository\SteamGameRepository;
use App\Service\Game\GameMapper;
use App\Service\GamePrice\GamePriceMapper;
use App\Service\Steam\PriceImportService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Мок GameRepository здесь и как стаб (готовые ответы findForAdminList/countForAdminList/find),
 * и как мок (проверка аргументов фильтров/сортировки/пагинации) — строгая проверка "мок без
 * expects()" отключена, как и в GameApiControllerTest (публичный API).
 */
#[AllowMockObjectsWithoutExpectations]
class GameApiControllerTest extends TestCase
{
    private GameRepository&MockObject $gameRepository;
    private SteamGameRepository&MockObject $steamGameRepository;
    private GamePriceRepository&MockObject $gamePriceRepository;
    private PriceImportService&MockObject $priceImportService;
    private GameMapper $gameMapper;
    private GamePriceMapper $gamePriceMapper;
    private GameApiController $controller;

    protected function setUp(): void
    {
        $this->gameRepository = $this->createMock(GameRepository::class);
        $this->steamGameRepository = $this->createMock(SteamGameRepository::class);
        $this->gamePriceRepository = $this->createMock(GamePriceRepository::class);
        $this->priceImportService = $this->createMock(PriceImportService::class);
        $this->gameMapper = new GameMapper();
        $this->gamePriceMapper = new GamePriceMapper();

        $this->controller = new GameApiController();
        // AbstractController::json() проверяет container->has('serializer') — пустой
        // контейнер без сервисов заставляет его отдать обычный JsonResponse.
        $this->controller->setContainer(new Container());
    }

    public function testListReturnsPageWithDefaultSortingAndPagination(): void
    {
        $gameWithCover = (new Game('Half-Life', 'half-life'))
            ->setDescription('A sci-fi shooter')
            ->setCoverImagePath('uploads/games/1.jpg')
            ->setMetacriticScore(96)
            ->setReleaseDate(new \DateTimeImmutable('1998-11-19'));
        $gameWithCover->addDeveloper(new Developer('Valve'));

        $this->gameRepository->method('countForAdminList')->willReturn(1);
        $this->gameRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'name', 'ASC', 25, 0)
            ->willReturn([$gameWithCover]);

        $response = $this->controller->list(new Request(), $this->gameRepository, $this->gameMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['total']);
        self::assertSame(1, $data['page']);
        self::assertSame(1, $data['totalPages']);
        self::assertSame([
            'id' => null,
            'name' => 'Half-Life',
            'slug' => 'half-life',
            'coverImageUrl' => '/uploads/games/1.jpg',
            'description' => 'A sci-fi shooter',
            'metacriticScore' => 96,
            'popularity' => null,
            'avgPopularity' => null,
            'releaseYear' => '1998',
            'developers' => ['Valve'],
            'publishers' => [],
            'genres' => [],
        ], $data['items'][0]);
    }

    public function testListPassesFiltersAndSortingToRepository(): void
    {
        $this->gameRepository->method('countForAdminList')->willReturn(0);
        $this->gameRepository->expects($this->once())
            ->method('findForAdminList')
            ->with(['name' => 'half', 'developer' => 'valve'], 'metacriticScore', 'DESC', 10, 0)
            ->willReturn([]);

        $request = new Request([
            'filters' => ['name' => ' half ', 'developer' => ' valve ', 'unknownField' => 'ignored'],
            'sortBy' => 'metacriticScore',
            'sortDir' => 'desc',
            'perPage' => '10',
        ]);
        $this->controller->list($request, $this->gameRepository, $this->gameMapper);
    }

    public function testListSortsByDevelopersOrPublishersWhenRequested(): void
    {
        $this->gameRepository->method('countForAdminList')->willReturn(0);
        $this->gameRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'publishers', 'ASC', 25, 0)
            ->willReturn([]);

        $request = new Request(['sortBy' => 'publishers']);
        $this->controller->list($request, $this->gameRepository, $this->gameMapper);
    }

    public function testListFallsBackToNameSortingForUnknownSortByAndClampsPerPage(): void
    {
        $this->gameRepository->method('countForAdminList')->willReturn(0);
        $this->gameRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'name', 'ASC', 100, 0)
            ->willReturn([]);

        $request = new Request(['sortBy' => 'unknownField', 'perPage' => '9999']);
        $this->controller->list($request, $this->gameRepository, $this->gameMapper);
    }

    public function testListClampsRequestedPageToTotalPages(): void
    {
        $this->gameRepository->method('countForAdminList')->willReturn(1);
        $this->gameRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'name', 'ASC', 25, 0)
            ->willReturn([]);

        $response = $this->controller->list(new Request(['page' => '999']), $this->gameRepository, $this->gameMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['page']);
        self::assertSame(1, $data['totalPages']);
    }

    public function testShowReturnsFullDetailWithRelatedEntityNames(): void
    {
        $game = (new Game('Day of Defeat', 'day-of-defeat'))
            ->setDescription('Team-based shooter')
            ->setRating(4.5)
            ->setMetacriticScore(80)
            ->setReleaseDate(new \DateTimeImmutable('2003-05-01'))
            ->setScreenshotUrls(['https://example.test/screenshot.jpg']);
        $game->addDeveloper(new Developer('Valve'));
        $game->addPublisher(new Publisher('Valve'));
        $game->addGenre(new Genre('Экшены'));
        $game->addPlatform(new Platform('Windows'));

        $this->gameRepository->expects($this->once())
            ->method('find')
            ->with(42)
            ->willReturn($game);

        $response = $this->controller->show(42, $this->gameRepository, $this->gameMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame('Day of Defeat', $data['name']);
        self::assertSame(['Valve'], $data['developers']);
        self::assertSame(['Экшены'], $data['genres']);
    }

    public function testShowThrowsNotFoundExceptionForUnknownId(): void
    {
        $this->gameRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->show(999, $this->gameRepository, $this->gameMapper);
    }

    public function testImportPriceReturnsPriceSnapshotOnSuccess(): void
    {
        $game = new Game('Half-Life', 'half-life');
        $steamGame = new SteamGame(70);
        $steamGame->setGame($game);
        $price = (new GamePrice($game, new \DateTimeImmutable('2026-09-15')))->markPriced(199900);

        $this->gameRepository->expects($this->once())->method('find')->with(42)->willReturn($game);
        $this->steamGameRepository->expects($this->once())
            ->method('findOneByGame')
            ->with($game)
            ->willReturn($steamGame);
        $this->priceImportService->expects($this->once())
            ->method('importPriceForGame')
            ->with($steamGame)
            ->willReturn($price);

        $response = $this->controller->importPrice(
            42,
            $this->gameRepository,
            $this->steamGameRepository,
            $this->priceImportService,
            $this->gamePriceMapper,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(199900, $data['priceKopecks']);
        self::assertSame('RUB', $data['currency']);
        self::assertFalse($data['isFree']);
        self::assertTrue($data['isAvailableInRussia']);
        self::assertSame('Steam', $data['store']);
        self::assertSame('https://store.steampowered.com/app/70/', $data['storeUrl']);
    }

    public function testImportPriceThrowsNotFoundExceptionForUnknownGame(): void
    {
        $this->gameRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->importPrice(
            999,
            $this->gameRepository,
            $this->steamGameRepository,
            $this->priceImportService,
            $this->gamePriceMapper,
        );
    }

    public function testImportPriceThrowsNotFoundExceptionWhenGameNotLinkedToSteam(): void
    {
        $game = new Game('Our Own Game', 'our-own-game');
        $this->gameRepository->method('find')->willReturn($game);
        $this->steamGameRepository->method('findOneByGame')->willReturn(null);
        $this->priceImportService->expects($this->never())->method('importPriceForGame');

        $this->expectException(NotFoundHttpException::class);

        $this->controller->importPrice(
            42,
            $this->gameRepository,
            $this->steamGameRepository,
            $this->priceImportService,
            $this->gamePriceMapper,
        );
    }

    public function testImportPriceReturnsBadGatewayWhenSteamRequestFails(): void
    {
        $game = new Game('Half-Life', 'half-life');
        $steamGame = new SteamGame(70);
        $steamGame->setGame($game);

        $this->gameRepository->method('find')->willReturn($game);
        $this->steamGameRepository->method('findOneByGame')->willReturn($steamGame);
        $this->priceImportService->method('importPriceForGame')->willReturn(null);

        $response = $this->controller->importPrice(
            42,
            $this->gameRepository,
            $this->steamGameRepository,
            $this->priceImportService,
            $this->gamePriceMapper,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(502, $response->getStatusCode());
        self::assertNotEmpty($data['errors']['steam']);
    }

    public function testPriceHistoryReturnsOrderedItemsWithStoreLink(): void
    {
        $game = new Game('Half-Life', 'half-life');
        $steamGame = new SteamGame(70);
        $steamGame->setGame($game);
        $older = (new GamePrice($game, new \DateTimeImmutable('2026-09-14')))->markPriced(199900);
        $newer = (new GamePrice($game, new \DateTimeImmutable('2026-09-15')))->markFree();

        $this->gameRepository->expects($this->once())->method('find')->with(42)->willReturn($game);
        $this->steamGameRepository->expects($this->once())
            ->method('findOneByGame')
            ->with($game)
            ->willReturn($steamGame);
        $this->gamePriceRepository->expects($this->once())
            ->method('findHistoryForGame')
            ->with($game)
            ->willReturn([$older, $newer]);

        $response = $this->controller->priceHistory(
            42,
            $this->gameRepository,
            $this->steamGameRepository,
            $this->gamePriceRepository,
            $this->gamePriceMapper,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertCount(2, $data['items']);
        self::assertSame('2026-09-14', $data['items'][0]['date']);
        self::assertSame('2026-09-15', $data['items'][1]['date']);
        self::assertTrue($data['items'][1]['isFree']);
        self::assertSame('Steam', $data['items'][0]['store']);
        self::assertSame('https://store.steampowered.com/app/70/', $data['items'][0]['storeUrl']);
    }

    public function testPriceHistoryReturnsEmptyItemsWhenNoHistory(): void
    {
        $game = new Game('New Game', 'new-game');
        $this->gameRepository->method('find')->willReturn($game);
        $this->steamGameRepository->method('findOneByGame')->willReturn(null);
        $this->gamePriceRepository->method('findHistoryForGame')->willReturn([]);

        $response = $this->controller->priceHistory(
            42,
            $this->gameRepository,
            $this->steamGameRepository,
            $this->gamePriceRepository,
            $this->gamePriceMapper,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame([], $data['items']);
    }

    public function testPriceHistoryThrowsNotFoundExceptionForUnknownGame(): void
    {
        $this->gameRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->priceHistory(
            999,
            $this->gameRepository,
            $this->steamGameRepository,
            $this->gamePriceRepository,
            $this->gamePriceMapper,
        );
    }
}
