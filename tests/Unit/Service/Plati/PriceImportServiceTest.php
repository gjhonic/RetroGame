<?php

namespace App\Tests\Unit\Service\Plati;

use App\Entity\Game;
use App\Entity\PlatiGame;
use App\Entity\PlatiGamePrice;
use App\Entity\PlatiPriceImportCursor;
use App\Repository\PlatiGamePriceRepository;
use App\Repository\PlatiGameRepository;
use App\Repository\PlatiPriceImportCursorRepository;
use App\Service\Plati\Exceptions\PlatiApiException;
use App\Service\Plati\GameMatcher;
use App\Service\Plati\Interfaces\RateLimiterInterface;
use App\Service\Plati\PlatiClient;
use App\Service\Plati\PlatiSearchItem;
use App\Service\Plati\PriceImportService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Мок-объекты здесь намеренно используются и как стабы, и как моки
 * (проверка persist/flush/delay) — поэтому строгая проверка PHPUnit "мок
 * без expects()" отключена, как и в Steam\PriceImportServiceTest.
 */
#[AllowMockObjectsWithoutExpectations]
class PriceImportServiceTest extends TestCase
{
    private const string DEFAULT_URL = 'https://plati.market/itm/existing';

    private PlatiClient&MockObject $platiClient;
    private EntityManagerInterface&MockObject $entityManager;
    private PlatiGameRepository&MockObject $platiGameRepository;
    private PlatiGamePriceRepository&MockObject $platiGamePriceRepository;
    private PlatiPriceImportCursorRepository&MockObject $cursorRepository;
    private RateLimiterInterface&MockObject $rateLimiter;
    private PriceImportService $service;

    protected function setUp(): void
    {
        $this->platiClient = $this->createMock(PlatiClient::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->platiGameRepository = $this->createMock(PlatiGameRepository::class);
        $this->platiGamePriceRepository = $this->createMock(PlatiGamePriceRepository::class);
        $this->platiGamePriceRepository->method('findOneByPlatiGameAndDate')->willReturn(null);
        $this->cursorRepository = $this->createMock(PlatiPriceImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn(new PlatiPriceImportCursor());
        $this->rateLimiter = $this->createMock(RateLimiterInterface::class);

        $this->service = $this->newService();
    }

    /** Собирает сервис из текущих моков — для тестов, переопределяющих cursorRepository/platiGamePriceRepository. */
    private function newService(): PriceImportService
    {
        return new PriceImportService(
            $this->platiClient,
            $this->entityManager,
            $this->platiGameRepository,
            $this->platiGamePriceRepository,
            $this->cursorRepository,
            $this->rateLimiter,
            new GameMatcher(),
        );
    }

    /** Создаёт PlatiGame с заданным id (обычно проставляется Doctrine при persist) — нужен курсору. */
    private static function makePlatiGame(int $id, Game $game, string $url = self::DEFAULT_URL): PlatiGame
    {
        $platiGame = new PlatiGame($game, $url, 'Seller');
        (new \ReflectionProperty($platiGame, 'id'))->setValue($platiGame, $id);

        return $platiGame;
    }

    private static function item(
        string $name,
        int $cntSell,
        ?int $priceRur,
        string $url = self::DEFAULT_URL,
    ): PlatiSearchItem {
        return new PlatiSearchItem(
            id: $url,
            name: $name,
            nameEng: $name,
            url: $url,
            cntSell: $cntSell,
            priceRur: $priceRur,
        );
    }

    /** Переносит updatedAt курсора на вчера — имитирует первый запуск крона в новый день. */
    private static function makeCursorUpdatedAtYesterday(PlatiPriceImportCursor $cursor): void
    {
        (new \ReflectionProperty($cursor, 'updatedAt'))->setValue($cursor, new \DateTimeImmutable('yesterday'));
    }

    public function testImportNextBatchMarksPricedGameWhenMatchFound(): void
    {
        $game = new Game('Half-Life', 'half-life');
        $platiGame = self::makePlatiGame(1, $game);
        $this->platiGameRepository->method('findBatchForPriceImport')->willReturn([$platiGame]);
        $this->platiClient->method('search')->willReturn([
            self::item('Half-Life STEAM Gift', 50, 199),
        ]);

        $result = $this->service->importNextBatch(5, 500);

        $price = $result->prices[0];
        self::assertTrue($price->isFound());
        self::assertSame(19900, $price->getPriceKopecks());
    }

    public function testImportNextBatchMarksUnavailableWhenNoMatch(): void
    {
        $game = new Game('Rust', 'rust');
        $platiGame = self::makePlatiGame(1, $game);
        $this->platiGameRepository->method('findBatchForPriceImport')->willReturn([$platiGame]);
        $this->platiClient->method('search')->willReturn([]);

        $result = $this->service->importNextBatch(5, 500);

        $price = $result->prices[0];
        self::assertFalse($price->isFound());
        self::assertNull($price->getPriceKopecks());
    }

    public function testImportNextBatchMarksUnavailableWhenMatchHasNoPrice(): void
    {
        $game = new Game('Rust', 'rust');
        $platiGame = self::makePlatiGame(1, $game);
        $this->platiGameRepository->method('findBatchForPriceImport')->willReturn([$platiGame]);
        $this->platiClient->method('search')->willReturn([
            self::item('Rust STEAM Gift', 50, null),
        ]);

        $result = $this->service->importNextBatch(5, 500);

        self::assertFalse($result->prices[0]->isFound());
    }

    public function testImportNextBatchMarksUnavailableWhenSearchFindsOnlyOtherSellersUrl(): void
    {
        // У игры несколько продавцов (PlatiGame), у каждого своя ссылка.
        // Поиск снова вернул объявления, но ни одно не совпадает по
        // ссылке именно с этим продавцом — значит его карточка пропала
        // из выдачи, а не то, что у неё цена другого продавца.
        $game = new Game('Half-Life', 'half-life');
        $platiGame = self::makePlatiGame(1, $game, 'https://plati.market/itm/this-seller');
        $this->platiGameRepository->method('findBatchForPriceImport')->willReturn([$platiGame]);
        $this->platiClient->method('search')->willReturn([
            self::item('Half-Life STEAM Gift', 999, 500, 'https://plati.market/itm/other-seller'),
        ]);

        $result = $this->service->importNextBatch(5, 500);

        self::assertFalse($result->prices[0]->isFound());
    }

    public function testImportNextBatchSkipsGameOnPlatiApiExceptionAndContinuesWithRest(): void
    {
        $flakyGame = self::makePlatiGame(1, new Game('Flaky Game', 'flaky-game'), 'https://plati.market/itm/flaky');
        $okGame = self::makePlatiGame(2, new Game('OK Game', 'ok-game'), 'https://plati.market/itm/ok');
        $this->platiGameRepository->method('findBatchForPriceImport')->willReturn([$flakyGame, $okGame]);

        $this->platiClient->method('search')->willReturnCallback(
            static function (string $query): array {
                if ($query === 'Flaky Game') {
                    throw new PlatiApiException('network error');
                }

                return [self::item('OK Game Steam Gift', 10, 500, 'https://plati.market/itm/ok')];
            },
        );

        $this->entityManager->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(PlatiGamePrice::class));

        $result = $this->service->importNextBatch(5, 500);

        self::assertCount(1, $result->prices);
        self::assertSame(1, $result->skippedCount);
        self::assertSame('OK Game', $result->prices[0]->getGame()->getName());
    }

    public function testImportNextBatchUpdatesExistingPriceRecordForSameDayInsteadOfCreatingNew(): void
    {
        $game = new Game('Existing Game', 'existing-game');
        $platiGame = self::makePlatiGame(1, $game);
        $existingPrice = new PlatiGamePrice($platiGame, new \DateTimeImmutable('today'));
        $this->platiGamePriceRepository = $this->createMock(PlatiGamePriceRepository::class);
        $this->platiGamePriceRepository->method('findOneByPlatiGameAndDate')->willReturn($existingPrice);
        $this->service = $this->newService();

        $this->platiGameRepository->method('findBatchForPriceImport')->willReturn([$platiGame]);
        $this->platiClient->method('search')->willReturn([self::item('Existing Game Gift', 10, 100)]);

        $this->entityManager->expects($this->never())->method('persist');

        $result = $this->service->importNextBatch(5, 500);

        self::assertSame($existingPrice, $result->prices[0]);
        self::assertSame(10000, $existingPrice->getPriceKopecks());
    }

    public function testImportNextBatchDelaysBetweenItemsButNotAfterTheLastOne(): void
    {
        $game1 = self::makePlatiGame(1, new Game('A', 'a'));
        $game2 = self::makePlatiGame(2, new Game('B', 'b'));
        $this->platiGameRepository->method('findBatchForPriceImport')->willReturn([$game1, $game2]);
        $this->platiClient->method('search')->willReturn([]);

        $this->rateLimiter->expects($this->once())->method('delay')->with(500);

        $this->service->importNextBatch(5, 500);
    }

    public function testImportNextBatchUsesPersistedCursorAsStartingPoint(): void
    {
        $cursor = (new PlatiPriceImportCursor())->setPosition(500, 999);
        $this->cursorRepository = $this->createMock(PlatiPriceImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn($cursor);
        $this->service = $this->newService();

        $platiGame = self::makePlatiGame(1000, new Game('After Cursor', 'after-cursor'));
        $this->platiGameRepository->expects($this->once())->method('findBatchForPriceImport')
            ->with(500, 999, 5)
            ->willReturn([$platiGame]);
        $this->platiClient->method('search')->willReturn([]);

        $this->service->importNextBatch(5, 500);
    }

    public function testImportNextBatchAdvancesCursorToLastProcessedGamePopularityAndId(): void
    {
        $cursor = new PlatiPriceImportCursor();
        $this->cursorRepository = $this->createMock(PlatiPriceImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn($cursor);
        $this->service = $this->newService();

        $gameB = (new Game('B', 'b'))->setPopularity(42);
        $game1 = self::makePlatiGame(10, new Game('A', 'a'));
        $game2 = self::makePlatiGame(20, $gameB);
        $this->platiGameRepository->method('findBatchForPriceImport')->willReturn([$game1, $game2]);
        $this->platiClient->method('search')->willReturn([]);

        $this->service->importNextBatch(5, 500);

        self::assertSame(20, $cursor->getLastPlatiGameId());
        self::assertSame(42, $cursor->getLastPopularity());
    }

    public function testImportNextBatchStoresPopularityAsMinusOneWhenLastGameHasNone(): void
    {
        $cursor = new PlatiPriceImportCursor();
        $this->cursorRepository = $this->createMock(PlatiPriceImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn($cursor);
        $this->service = $this->newService();

        $platiGame = self::makePlatiGame(1, new Game('No Popularity Game', 'no-popularity-game'));
        $this->platiGameRepository->method('findBatchForPriceImport')->willReturn([$platiGame]);
        $this->platiClient->method('search')->willReturn([]);

        $this->service->importNextBatch(5, 500);

        self::assertSame(-1, $cursor->getLastPopularity());
    }

    public function testImportNextBatchReturnsEmptyResultWhenQueueIsEmpty(): void
    {
        $this->platiGameRepository->method('findBatchForPriceImport')->willReturn([]);

        $this->entityManager->expects($this->never())->method('persist');
        $this->rateLimiter->expects($this->never())->method('delay');

        $result = $this->service->importNextBatch(5, 500);

        self::assertSame([], $result->prices);
        self::assertSame(0, $result->skippedCount);
    }

    public function testImportNextBatchResetsCursorOnFirstRunOfNewDay(): void
    {
        $cursor = (new PlatiPriceImportCursor())->setPosition(500, 999);
        self::makeCursorUpdatedAtYesterday($cursor);
        $this->cursorRepository = $this->createMock(PlatiPriceImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn($cursor);
        $this->service = $this->newService();

        $platiGame = self::makePlatiGame(5, new Game('Most Popular Today', 'most-popular-today'));
        $this->platiGameRepository->expects($this->once())->method('findBatchForPriceImport')
            ->with(null, 0, 5)
            ->willReturn([$platiGame]);
        $this->platiClient->method('search')->willReturn([]);

        $result = $this->service->importNextBatch(5, 500);

        self::assertTrue($result->startedNewDay);
        self::assertSame(5, $cursor->getLastPlatiGameId());
    }
}
