<?php

namespace App\Tests\Unit\Service\Plati;

use App\Entity\Game;
use App\Entity\PlatiGame;
use App\Entity\PlatiImportCursor;
use App\Repository\PlatiGameRepository;
use App\Repository\PlatiImportCursorRepository;
use App\Service\Plati\Exceptions\PlatiApiException;
use App\Service\Plati\GameImportService;
use App\Service\Plati\GameMatcher;
use App\Service\Plati\Interfaces\RateLimiterInterface;
use App\Service\Plati\PlatiClient;
use App\Service\Plati\PlatiSearchItem;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Мок-объекты здесь намеренно используются и как стабы (готовые ответы
 * PlatiClient/репозиториев), и как моки (проверка persist/flush/delay) —
 * поэтому строгая проверка PHPUnit "мок без expects()" отключена, как и в
 * Steam\GameImportServiceTest.
 */
#[AllowMockObjectsWithoutExpectations]
class GameImportServiceTest extends TestCase
{
    private PlatiClient&MockObject $platiClient;
    private EntityManagerInterface&MockObject $entityManager;
    private PlatiGameRepository&MockObject $platiGameRepository;
    private PlatiImportCursorRepository&MockObject $cursorRepository;
    private RateLimiterInterface&MockObject $rateLimiter;
    private GameImportService $service;

    protected function setUp(): void
    {
        $this->platiClient = $this->createMock(PlatiClient::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->platiGameRepository = $this->createMock(PlatiGameRepository::class);
        $this->platiGameRepository->method('findOneByGameAndUrl')->willReturn(null);
        $this->cursorRepository = $this->createMock(PlatiImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn(new PlatiImportCursor());
        $this->rateLimiter = $this->createMock(RateLimiterInterface::class);

        $this->service = $this->newService();
    }

    /** Собирает сервис из текущих моков — для тестов, переопределяющих отдельные зависимости. */
    private function newService(): GameImportService
    {
        return new GameImportService(
            $this->platiClient,
            $this->entityManager,
            $this->platiGameRepository,
            $this->cursorRepository,
            $this->rateLimiter,
            new GameMatcher(),
        );
    }

    /** Создаёт Game с заданным id (обычно проставляется Doctrine при persist) — нужен курсору. */
    private static function makeGame(int $id, string $name, ?int $popularity = null): Game
    {
        $game = new Game($name, strtolower($name));
        $game->setPopularity($popularity);
        (new \ReflectionProperty($game, 'id'))->setValue($game, $id);

        return $game;
    }

    private static function item(
        string $name,
        string $url,
        int $cntSell,
        ?string $nameEng = null,
        string $sellerName = 'Seller',
    ): PlatiSearchItem {
        return new PlatiSearchItem(
            id: $url,
            name: $name,
            nameEng: $nameEng ?? $name,
            url: $url,
            cntSell: $cntSell,
            sellerName: $sellerName,
        );
    }

    public function testImportNextBatchCreatesPlatiGamesFromUpToThreeBestSellingMatches(): void
    {
        $game = self::makeGame(1, 'Half-Life', 100);
        $this->platiGameRepository->method('findGamesPendingCheck')->willReturn([$game]);

        $this->platiClient->expects($this->once())->method('search')
            ->with('Half-Life', 10)
            ->willReturn([
                self::item('Half-Life STEAM Gift', 'https://plati.market/itm/1', 50, sellerName: 'Seller1'),
                self::item('Half-Life 2 STEAM Gift', 'https://plati.market/itm/2', 9999, sellerName: 'Seller2'),
                self::item('Half-Life STEAM Автодоставка', 'https://plati.market/itm/3', 200, sellerName: 'Seller3'),
                self::item('Half-Life Steam Key', 'https://plati.market/itm/4', 20, sellerName: 'Seller4'),
            ]);

        $this->entityManager->expects($this->exactly(3))->method('persist')
            ->with($this->isInstanceOf(PlatiGame::class));

        $result = $this->service->importNextBatch(20, 500);

        self::assertTrue($result->results[0]->isFound());
        // Все 4 объявления содержат "half life" как подстроку (наивное
        // совпадение цепляет и "Half-Life 2"), но сохраняются только 3
        // самых продаваемых, в порядке убывания продаж.
        self::assertSame(
            ['https://plati.market/itm/2', 'https://plati.market/itm/3', 'https://plati.market/itm/1'],
            array_map(static fn (PlatiGame $pg): string => $pg->getUrl(), $result->results[0]->platiGames),
        );
    }

    public function testImportNextBatchStoresSellerNameFromMatchedItem(): void
    {
        $game = self::makeGame(1, "Garry's Mod", 100);
        $this->platiGameRepository->method('findGamesPendingCheck')->willReturn([$game]);

        $this->platiClient->method('search')->willReturn([
            self::item('Garrys Mod STEAM•RU ⚡️АВТОДОСТАВКА', 'https://plati.market/itm/1', 3143, sellerName: 'DarkAwe'),
        ]);

        $result = $this->service->importNextBatch(20, 500);

        self::assertSame('DarkAwe', $result->results[0]->platiGames[0]->getSellerName());
    }

    public function testImportNextBatchImportsSingleSellerWhenOnlyOneMatchFound(): void
    {
        $game = self::makeGame(1, 'Rust', 100);
        $this->platiGameRepository->method('findGamesPendingCheck')->willReturn([$game]);

        $this->platiClient->method('search')->willReturn([
            self::item('Counter-Strike 2 Prime Status', 'https://plati.market/itm/1', 500),
            self::item('RUST (STEAM GIFT RU/CIS)', 'https://plati.market/itm/2', 300),
        ]);

        $result = $this->service->importNextBatch(20, 500);

        self::assertTrue($result->results[0]->isFound());
        self::assertCount(1, $result->results[0]->platiGames);
        self::assertSame('https://plati.market/itm/2', $result->results[0]->platiGames[0]->getUrl());
    }

    public function testImportNextBatchMatchesViaApostropheInsensitiveNormalization(): void
    {
        // Реальный случай: "Garry's Mod" (апостроф) должно совпасть с
        // объявлением "Garrys Mod" (без апострофа) и наоборот.
        $game = self::makeGame(1, "Garry's Mod", 100);
        $this->platiGameRepository->method('findGamesPendingCheck')->willReturn([$game]);

        $this->platiClient->method('search')->willReturn([
            self::item('Garrys Mod STEAM•RU ⚡️АВТОДОСТАВКА', 'https://plati.market/itm/1', 3143),
        ]);

        $result = $this->service->importNextBatch(20, 500);

        self::assertTrue($result->results[0]->isFound());
    }

    public function testImportNextBatchReturnsNotFoundWhenSearchHasNoResults(): void
    {
        $game = self::makeGame(1, 'Unknown Game', 5);
        $this->platiGameRepository->method('findGamesPendingCheck')->willReturn([$game]);

        $this->platiClient->method('search')->willReturn([]);

        $this->entityManager->expects($this->never())->method('persist');

        $result = $this->service->importNextBatch(20, 500);

        self::assertFalse($result->results[0]->isFound());
        self::assertSame('ничего не найдено по запросу', $result->results[0]->reason);
    }

    public function testImportNextBatchReturnsNotFoundWhenNoResultsMatchGameName(): void
    {
        $game = self::makeGame(1, 'Very Specific Game Name', 5);
        $this->platiGameRepository->method('findGamesPendingCheck')->willReturn([$game]);

        $this->platiClient->method('search')->willReturn([
            self::item('Something Completely Different', 'https://plati.market/itm/1', 100),
        ]);

        $result = $this->service->importNextBatch(20, 500);

        self::assertFalse($result->results[0]->isFound());
        self::assertStringContainsString('нет совпадений по названию', $result->results[0]->reason);
    }

    public function testImportNextBatchSkipsGameOnPlatiApiExceptionAndContinuesWithRest(): void
    {
        $flakyGame = self::makeGame(1, 'Flaky Game', 100);
        $okGame = self::makeGame(2, 'OK Game', 50);
        $this->platiGameRepository->method('findGamesPendingCheck')->willReturn([$flakyGame, $okGame]);

        $calls = 0;
        $this->platiClient->method('search')->willReturnCallback(
            static function () use (&$calls): array {
                ++$calls;

                if ($calls === 1) {
                    throw new PlatiApiException('network error');
                }

                return [self::item('OK Game Steam Gift', 'https://plati.market/itm/1', 10)];
            },
        );

        $result = $this->service->importNextBatch(20, 500);

        self::assertFalse($result->results[0]->isFound());
        self::assertStringContainsString('ошибка запроса', $result->results[0]->reason);
        self::assertTrue($result->results[1]->isFound());
    }

    public function testImportNextBatchUpdatesExistingPlatiGameByUrlInsteadOfCreatingNew(): void
    {
        $game = self::makeGame(1, 'Half-Life', 100);
        $existing = new PlatiGame($game, 'https://plati.market/itm/1', 'Old Seller Name');

        // Отдельный мок вместо переопределения findOneByGameAndUrl на общем —
        // у общего уже есть стаб "null" из setUp(), а PHPUnit при повторном
        // stubbing того же метода без with() отдаёт приоритет первому
        // сработавшему совпадению, а не последнему добавленному.
        $this->platiGameRepository = $this->createMock(PlatiGameRepository::class);
        $this->platiGameRepository->method('findGamesPendingCheck')->willReturn([$game]);
        $this->platiGameRepository->method('findOneByGameAndUrl')->willReturnCallback(
            static fn (Game $g, string $url): ?PlatiGame => $url === 'https://plati.market/itm/1' ? $existing : null,
        );
        $this->service = $this->newService();

        $this->platiClient->method('search')->willReturn([
            self::item('Half-Life Steam Gift', 'https://plati.market/itm/1', 10, sellerName: 'New Seller Name'),
        ]);

        $this->entityManager->expects($this->never())->method('persist');

        $result = $this->service->importNextBatch(20, 500);

        self::assertSame($existing, $result->results[0]->platiGames[0]);
        self::assertSame('New Seller Name', $existing->getSellerName());
    }

    public function testImportNextBatchDelaysBetweenItemsButNotAfterTheLastOne(): void
    {
        $game1 = self::makeGame(1, 'A', 10);
        $game2 = self::makeGame(2, 'B', 5);
        $this->platiGameRepository->method('findGamesPendingCheck')->willReturn([$game1, $game2]);
        $this->platiClient->method('search')->willReturn([]);

        $this->rateLimiter->expects($this->once())->method('delay')->with(500);

        $this->service->importNextBatch(20, 500);
    }

    public function testImportNextBatchAdvancesCursorToLastCheckedGamePopularityAndId(): void
    {
        $cursor = new PlatiImportCursor();
        $this->cursorRepository = $this->createMock(PlatiImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn($cursor);
        $this->service = $this->newService();

        $game1 = self::makeGame(1, 'A', 10);
        $game2 = self::makeGame(2, 'B', 5);
        $this->platiGameRepository->method('findGamesPendingCheck')->willReturn([$game1, $game2]);
        $this->platiClient->method('search')->willReturn([]);

        $this->service->importNextBatch(20, 500);

        self::assertSame(5, $cursor->getLastPopularity());
        self::assertSame(2, $cursor->getLastGameId());
    }

    public function testImportNextBatchStoresPopularityAsMinusOneWhenLastGameHasNone(): void
    {
        $cursor = new PlatiImportCursor();
        $this->cursorRepository = $this->createMock(PlatiImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn($cursor);
        $this->service = $this->newService();

        $game = self::makeGame(1, 'No Popularity Game', null);
        $this->platiGameRepository->method('findGamesPendingCheck')->willReturn([$game]);
        $this->platiClient->method('search')->willReturn([]);

        $this->service->importNextBatch(20, 500);

        self::assertSame(-1, $cursor->getLastPopularity());
    }

    public function testImportNextBatchReturnsEmptyResultWhenNothingLeftToCheckEvenAfterWrap(): void
    {
        $cursor = (new PlatiImportCursor())->setPosition(42, 7);
        $this->cursorRepository = $this->createMock(PlatiImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn($cursor);
        $this->service = $this->newService();

        $this->platiGameRepository->method('findGamesPendingCheck')->willReturn([]);

        $this->entityManager->expects($this->never())->method('flush');

        $result = $this->service->importNextBatch(20, 500);

        self::assertSame(0, $result->checkedCount());
        self::assertTrue($result->wrapped);
        self::assertNull($cursor->getLastPopularity());
    }

    public function testImportNextBatchWrapsAndChecksGamesWhenBatchEmptyMidCursor(): void
    {
        $cursor = (new PlatiImportCursor())->setPosition(42, 7);
        $this->cursorRepository = $this->createMock(PlatiImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn($cursor);
        $this->service = $this->newService();

        $game = self::makeGame(1, 'A', 10);
        $this->platiGameRepository->expects($this->exactly(2))->method('findGamesPendingCheck')
            ->willReturnOnConsecutiveCalls([], [$game]);
        $this->platiClient->method('search')->willReturn([]);

        $result = $this->service->importNextBatch(20, 500);

        self::assertSame(1, $result->checkedCount());
        self::assertTrue($result->wrapped);
    }

    public function testImportNextBatchReturnsEmptyResultWithoutWrapWhenCursorAlreadyAtStart(): void
    {
        $this->platiGameRepository->method('findGamesPendingCheck')->willReturn([]);

        $this->entityManager->expects($this->never())->method('flush');

        $result = $this->service->importNextBatch(20, 500);

        self::assertSame(0, $result->checkedCount());
        self::assertFalse($result->wrapped);
    }
}
