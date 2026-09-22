<?php

namespace App\Tests\Unit\Controller\Api\Admin;

use App\Controller\Api\Admin\GameApiController;
use App\Entity\Developer;
use App\Entity\Game;
use App\Entity\Genre;
use App\Entity\GamePrice;
use App\Entity\PlatiGame;
use App\Entity\PlatiGamePrice;
use App\Entity\Platform;
use App\Entity\Publisher;
use App\Entity\SteamGame;
use App\Repository\GamePriceRepository;
use App\Repository\GameRepository;
use App\Repository\PlatiGamePriceRepository;
use App\Repository\PlatiGameRepository;
use App\Repository\SteamGameRepository;
use App\Service\Game\GameMapper;
use App\Service\GamePrice\GamePriceMapper;
use App\Service\Plati\GameImportService as PlatiGameImportService;
use App\Service\Plati\PlatiCheckResult;
use App\Service\Plati\PriceImportService as PlatiPriceImportService;
use App\Service\PlatiGame\PlatiGameMapper;
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
    private PlatiGameRepository&MockObject $platiGameRepository;
    private PlatiGamePriceRepository&MockObject $platiGamePriceRepository;
    private PriceImportService&MockObject $priceImportService;
    private PlatiGameImportService&MockObject $platiGameImportService;
    private PlatiPriceImportService&MockObject $platiPriceImportService;
    private GameMapper $gameMapper;
    private GamePriceMapper $gamePriceMapper;
    private PlatiGameMapper $platiGameMapper;
    private GameApiController $controller;

    protected function setUp(): void
    {
        $this->gameRepository = $this->createMock(GameRepository::class);
        $this->steamGameRepository = $this->createMock(SteamGameRepository::class);
        $this->gamePriceRepository = $this->createMock(GamePriceRepository::class);
        $this->platiGameRepository = $this->createMock(PlatiGameRepository::class);
        $this->platiGamePriceRepository = $this->createMock(PlatiGamePriceRepository::class);
        $this->priceImportService = $this->createMock(PriceImportService::class);
        $this->platiGameImportService = $this->createMock(PlatiGameImportService::class);
        $this->platiPriceImportService = $this->createMock(PlatiPriceImportService::class);
        $this->gameMapper = new GameMapper();
        $this->gamePriceMapper = new GamePriceMapper();
        $this->platiGameMapper = new PlatiGameMapper();

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
        $this->steamGameRepository->method('findOneByGame')->willReturn(null);
        $this->platiGameRepository->method('findByGame')->willReturn([]);

        $response = $this->controller->show(
            42,
            $this->gameRepository,
            $this->gameMapper,
            $this->steamGameRepository,
            $this->platiGameRepository,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame('Day of Defeat', $data['name']);
        self::assertSame(['Valve'], $data['developers']);
        self::assertSame(['Экшены'], $data['genres']);
        self::assertNull($data['steamGame']);
        self::assertSame([], $data['platiGames']);
    }

    public function testShowIncludesLinkedSteamGameAndPlatiGames(): void
    {
        $game = new Game('Half-Life', 'half-life');
        $steamGame = new SteamGame(70);
        $steamGame->setGame($game);
        $platiGame = new PlatiGame($game, 'https://plati.market/itm/123', 'BestSeller', 'Half-Life Steam Gift');

        $this->gameRepository->method('find')->willReturn($game);
        $this->steamGameRepository->method('findOneByGame')->willReturn($steamGame);
        $this->platiGameRepository->method('findByGame')->willReturn([$platiGame]);

        $response = $this->controller->show(
            42,
            $this->gameRepository,
            $this->gameMapper,
            $this->steamGameRepository,
            $this->platiGameRepository,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(70, $data['steamGame']['steamAppId']);
        self::assertCount(1, $data['platiGames']);
        self::assertSame('BestSeller', $data['platiGames'][0]['sellerName']);
        self::assertSame('Half-Life Steam Gift', $data['platiGames'][0]['platiName']);
        self::assertSame('https://plati.market/itm/123', $data['platiGames'][0]['url']);
    }

    public function testShowThrowsNotFoundExceptionForUnknownId(): void
    {
        $this->gameRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->show(
            999,
            $this->gameRepository,
            $this->gameMapper,
            $this->steamGameRepository,
            $this->platiGameRepository,
        );
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

    public function testPriceHistoryReturnsSteamAndPlatiHistory(): void
    {
        $game = new Game('Half-Life', 'half-life');
        $steamGame = new SteamGame(70);
        $steamGame->setGame($game);
        $older = (new GamePrice($game, new \DateTimeImmutable('2026-09-14')))->markPriced(199900);
        $newer = (new GamePrice($game, new \DateTimeImmutable('2026-09-15')))->markFree();

        $platiGame = new PlatiGame($game, 'https://plati.market/itm/123', 'BestSeller', 'Half-Life Steam Gift');
        $platiPrice = new PlatiGamePrice($platiGame, new \DateTimeImmutable('2026-09-15'));
        $platiPrice->markPriced(150000);

        $this->gameRepository->expects($this->once())->method('find')->with(42)->willReturn($game);
        $this->steamGameRepository->expects($this->once())
            ->method('findOneByGame')
            ->with($game)
            ->willReturn($steamGame);
        $this->gamePriceRepository->expects($this->once())
            ->method('findHistoryForGame')
            ->with($game)
            ->willReturn([$older, $newer]);
        $this->platiGameRepository->expects($this->once())
            ->method('findByGame')
            ->with($game)
            ->willReturn([$platiGame]);
        $this->platiGamePriceRepository->expects($this->once())
            ->method('findHistoryForPlatiGame')
            ->with($platiGame)
            ->willReturn([$platiPrice]);

        $response = $this->controller->priceHistory(
            42,
            $this->gameRepository,
            $this->steamGameRepository,
            $this->gamePriceRepository,
            $this->gamePriceMapper,
            $this->platiGameRepository,
            $this->platiGamePriceRepository,
            $this->platiGameMapper,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertCount(2, $data['steam']['history']);
        self::assertSame('2026-09-14', $data['steam']['history'][0]['date']);
        self::assertTrue($data['steam']['isFree']);
        self::assertSame('Steam', $data['steam']['store']);
        self::assertSame('https://store.steampowered.com/app/70/', $data['steam']['storeUrl']);

        self::assertCount(1, $data['plati']);
        self::assertSame('BestSeller', $data['plati'][0]['sellerName']);
        self::assertSame(150000, $data['plati'][0]['priceKopecks']);
    }

    public function testPriceHistoryReturnsEmptyHistoryWhenNoData(): void
    {
        $game = new Game('New Game', 'new-game');
        $this->gameRepository->method('find')->willReturn($game);
        $this->steamGameRepository->method('findOneByGame')->willReturn(null);
        $this->gamePriceRepository->method('findHistoryForGame')->willReturn([]);
        $this->platiGameRepository->method('findByGame')->willReturn([]);

        $response = $this->controller->priceHistory(
            42,
            $this->gameRepository,
            $this->steamGameRepository,
            $this->gamePriceRepository,
            $this->gamePriceMapper,
            $this->platiGameRepository,
            $this->platiGamePriceRepository,
            $this->platiGameMapper,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame([], $data['steam']['history']);
        self::assertSame([], $data['plati']);
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
            $this->platiGameRepository,
            $this->platiGamePriceRepository,
            $this->platiGameMapper,
        );
    }

    public function testImportPlatiReturnsUpdatedSellersListOnSuccess(): void
    {
        $game = new Game('Half-Life', 'half-life');
        $platiGame = new PlatiGame($game, 'https://plati.market/itm/123', 'BestSeller', 'Half-Life Steam Gift');

        $this->gameRepository->expects($this->once())->method('find')->with(42)->willReturn($game);
        $this->platiGameImportService->expects($this->once())
            ->method('importForGame')
            ->with($game)
            ->willReturn(new PlatiCheckResult(
                $game,
                [$platiGame],
                'совпадений по названию: 1, сохранено продавцов: 1 (по убыванию продаж)',
            ));
        $this->platiGameRepository->expects($this->once())
            ->method('findByGame')
            ->with($game)
            ->willReturn([$platiGame]);

        $response = $this->controller->importPlati(
            42,
            $this->gameRepository,
            $this->platiGameRepository,
            $this->platiGameImportService,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertTrue($data['found']);
        self::assertStringContainsString('сохранено продавцов: 1', $data['message']);
        self::assertCount(1, $data['platiGames']);
        self::assertSame('BestSeller', $data['platiGames'][0]['sellerName']);
        self::assertSame('Half-Life Steam Gift', $data['platiGames'][0]['platiName']);
    }

    public function testImportPlatiReturnsNotFoundReasonWhenNoMatches(): void
    {
        $game = new Game('Very Specific Game', 'very-specific-game');

        $this->gameRepository->method('find')->willReturn($game);
        $this->platiGameImportService->method('importForGame')
            ->willReturn(new PlatiCheckResult($game, [], 'ничего не найдено по запросу'));
        $this->platiGameRepository->method('findByGame')->willReturn([]);

        $response = $this->controller->importPlati(
            42,
            $this->gameRepository,
            $this->platiGameRepository,
            $this->platiGameImportService,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertFalse($data['found']);
        self::assertSame('ничего не найдено по запросу', $data['message']);
        self::assertSame([], $data['platiGames']);
    }

    public function testImportPlatiThrowsNotFoundExceptionForUnknownGame(): void
    {
        $this->gameRepository->method('find')->willReturn(null);
        $this->platiGameImportService->expects($this->never())->method('importForGame');

        $this->expectException(NotFoundHttpException::class);

        $this->controller->importPlati(
            999,
            $this->gameRepository,
            $this->platiGameRepository,
            $this->platiGameImportService,
        );
    }

    public function testImportPlatiPricesReturnsImportedAndSkippedCounts(): void
    {
        $game = new Game('Half-Life', 'half-life');
        $seller1 = new PlatiGame($game, 'https://plati.market/itm/1', 'Seller1', 'Half-Life Gift');
        $seller2 = new PlatiGame($game, 'https://plati.market/itm/2', 'Seller2', 'Half-Life Key');
        $price = new PlatiGamePrice($seller1, new \DateTimeImmutable('2026-09-15'));
        $price->markPriced(19900);

        $this->gameRepository->expects($this->once())->method('find')->with(42)->willReturn($game);
        $this->platiGameRepository->expects($this->once())
            ->method('findByGame')
            ->with($game)
            ->willReturn([$seller1, $seller2]);
        $this->platiPriceImportService->expects($this->once())
            ->method('importPricesForGame')
            ->with($game)
            ->willReturn([$price]);

        $response = $this->controller->importPlatiPrices(
            42,
            $this->gameRepository,
            $this->platiGameRepository,
            $this->platiPriceImportService,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['importedCount']);
        self::assertSame(1, $data['skippedCount']);
    }

    public function testImportPlatiPricesThrowsNotFoundExceptionForUnknownGame(): void
    {
        $this->gameRepository->method('find')->willReturn(null);
        $this->platiPriceImportService->expects($this->never())->method('importPricesForGame');

        $this->expectException(NotFoundHttpException::class);

        $this->controller->importPlatiPrices(
            999,
            $this->gameRepository,
            $this->platiGameRepository,
            $this->platiPriceImportService,
        );
    }
}
