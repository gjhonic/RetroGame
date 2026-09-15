<?php

namespace App\Tests\Unit\Service\Steam;

use App\Entity\Game;
use App\Entity\GamePrice;
use App\Entity\SteamGame;
use App\Entity\SteamPriceImportCursor;
use App\Repository\GamePriceRepository;
use App\Repository\SteamGameRepository;
use App\Repository\SteamPriceImportCursorRepository;
use App\Service\Steam\Exceptions\SteamApiException;
use App\Service\Steam\Interfaces\RandomDelayRangeInterface;
use App\Service\Steam\Interfaces\RateLimiterInterface;
use App\Service\Steam\PriceImportService;
use App\Service\Steam\SteamClient;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Мок-объекты здесь намеренно используются и как стабы (готовые ответы
 * SteamClient/репозиториев), и как моки (проверка persist/flush/delay) —
 * поэтому строгая проверка PHPUnit "мок без expects()" отключена, как и в
 * GameImportServiceTest.
 */
#[AllowMockObjectsWithoutExpectations]
class PriceImportServiceTest extends TestCase
{
    private SteamClient&MockObject $steamClient;
    private EntityManagerInterface&MockObject $entityManager;
    private SteamGameRepository&MockObject $steamGameRepository;
    private GamePriceRepository&MockObject $gamePriceRepository;
    private SteamPriceImportCursorRepository&MockObject $cursorRepository;
    private RateLimiterInterface&MockObject $rateLimiter;
    private RandomDelayRangeInterface&MockObject $randomDelayRange;
    private PriceImportService $service;

    protected function setUp(): void
    {
        $this->steamClient = $this->createMock(SteamClient::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->steamGameRepository = $this->createMock(SteamGameRepository::class);
        $this->gamePriceRepository = $this->createMock(GamePriceRepository::class);
        $this->gamePriceRepository->method('findOneByGameAndDate')->willReturn(null);
        $this->cursorRepository = $this->createMock(SteamPriceImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn(new SteamPriceImportCursor());
        $this->rateLimiter = $this->createMock(RateLimiterInterface::class);
        $this->randomDelayRange = $this->createMock(RandomDelayRangeInterface::class);

        $this->service = $this->newService();
    }

    /** Собирает сервис из текущих моков — для тестов, переопределяющих cursorRepository/gamePriceRepository. */
    private function newService(): PriceImportService
    {
        return new PriceImportService(
            $this->steamClient,
            $this->entityManager,
            $this->steamGameRepository,
            $this->gamePriceRepository,
            $this->cursorRepository,
            $this->rateLimiter,
            $this->randomDelayRange,
        );
    }

    /** Создаёт SteamGame с заданным id (обычно проставляется Doctrine при persist) — нужен курсору. */
    private static function makeSteamGame(int $id, int $steamAppId, Game $game): SteamGame
    {
        $steamGame = new SteamGame($steamAppId);
        $steamGame->setGame($game);
        (new \ReflectionProperty($steamGame, 'id'))->setValue($steamGame, $id);

        return $steamGame;
    }

    /** Переносит updatedAt курсора на вчера — имитирует первый запуск крона в новый день. */
    private static function makeCursorUpdatedAtYesterday(SteamPriceImportCursor $cursor): void
    {
        (new \ReflectionProperty($cursor, 'updatedAt'))->setValue($cursor, new \DateTimeImmutable('yesterday'));
    }

    public function testImportNextBatchMarksPricedGameWhenPriceOverviewPresent(): void
    {
        $game = new Game('Half-Life', 'half-life');
        $steamGame = self::makeSteamGame(1, 10, $game);
        $this->steamGameRepository->method('findBatchForPriceImport')->willReturn([$steamGame]);
        $this->steamClient->method('fetchAppDetailsForRussia')->willReturn([
            'price_overview' => ['final' => 199900, 'currency' => 'RUB'],
        ]);

        $result = $this->service->importNextBatch(5, 1000, 1000);

        $price = $result->prices[0];
        self::assertFalse($price->isFree());
        self::assertTrue($price->isAvailableInRussia());
        self::assertSame(199900, $price->getPriceKopecks());
        self::assertTrue($steamGame->isAvailableInRussia());
    }

    public function testImportNextBatchMarksFreeGameWhenIsFreeTrue(): void
    {
        $game = new Game('Free Game', 'free-game');
        $steamGame = self::makeSteamGame(1, 20, $game);
        $this->steamGameRepository->method('findBatchForPriceImport')->willReturn([$steamGame]);
        $this->steamClient->method('fetchAppDetailsForRussia')->willReturn(['is_free' => true]);

        $result = $this->service->importNextBatch(5, 1000, 1000);

        $price = $result->prices[0];
        self::assertTrue($price->isFree());
        self::assertTrue($price->isAvailableInRussia());
        self::assertSame(0, $price->getPriceKopecks());
        self::assertTrue($steamGame->isPriceFree());
    }

    public function testImportNextBatchLeavesPriceFreeFalseWhenGameIsPaid(): void
    {
        $game = new Game('Half-Life', 'half-life');
        $steamGame = self::makeSteamGame(1, 10, $game);
        $this->steamGameRepository->method('findBatchForPriceImport')->willReturn([$steamGame]);
        $this->steamClient->method('fetchAppDetailsForRussia')->willReturn([
            'price_overview' => ['final' => 199900, 'currency' => 'RUB'],
        ]);

        $this->service->importNextBatch(5, 1000, 1000);

        self::assertFalse($steamGame->isPriceFree());
    }

    public function testImportNextBatchMarksUnavailableWhenDetailsAreNull(): void
    {
        $game = new Game('Locked Game', 'locked-game');
        $steamGame = self::makeSteamGame(1, 30, $game);
        $this->steamGameRepository->method('findBatchForPriceImport')->willReturn([$steamGame]);
        $this->steamClient->method('fetchAppDetailsForRussia')->willReturn(null);

        $result = $this->service->importNextBatch(5, 1000, 1000);

        $price = $result->prices[0];
        self::assertFalse($price->isFree());
        self::assertFalse($price->isAvailableInRussia());
        self::assertNull($price->getPriceKopecks());
        self::assertFalse($steamGame->isAvailableInRussia());
    }

    public function testImportNextBatchRestoresSteamGameAvailabilityWhenGameBecomesAvailableAgain(): void
    {
        $game = new Game('Previously Locked Game', 'previously-locked-game');
        $steamGame = self::makeSteamGame(1, 35, $game);
        $steamGame->setAvailableInRussia(false);
        $this->steamGameRepository->method('findBatchForPriceImport')->willReturn([$steamGame]);
        $this->steamClient->method('fetchAppDetailsForRussia')->willReturn([
            'price_overview' => ['final' => 19900, 'currency' => 'RUB'],
        ]);

        $this->service->importNextBatch(5, 1000, 1000);

        self::assertTrue($steamGame->isAvailableInRussia());
    }

    public function testImportNextBatchMarksUnavailableWhenNoPriceOverviewAndNotFree(): void
    {
        $game = new Game('No Price Game', 'no-price-game');
        $steamGame = self::makeSteamGame(1, 40, $game);
        $this->steamGameRepository->method('findBatchForPriceImport')->willReturn([$steamGame]);
        $this->steamClient->method('fetchAppDetailsForRussia')->willReturn(['name' => 'No Price Game']);

        $result = $this->service->importNextBatch(5, 1000, 1000);

        $price = $result->prices[0];
        self::assertFalse($price->isAvailableInRussia());
        self::assertNull($price->getPriceKopecks());
    }

    public function testImportNextBatchSkipsGameOnSteamApiExceptionAndContinuesWithRest(): void
    {
        $flakyGame = self::makeSteamGame(1, 100, new Game('Flaky Game', 'flaky-game'));
        $okGame = self::makeSteamGame(2, 200, new Game('OK Game', 'ok-game'));
        $this->steamGameRepository->method('findBatchForPriceImport')->willReturn([$flakyGame, $okGame]);

        $this->steamClient->method('fetchAppDetailsForRussia')->willReturnCallback(
            static function (int $appId): array {
                if ($appId === 100) {
                    throw new SteamApiException('Ошибка сети');
                }

                return ['price_overview' => ['final' => 500, 'currency' => 'RUB']];
            },
        );

        $this->entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(GamePrice::class));
        $this->entityManager->expects($this->exactly(2))->method('flush');

        $result = $this->service->importNextBatch(5, 1000, 1000);

        self::assertCount(1, $result->prices);
        self::assertSame(1, $result->skippedCount);
        self::assertSame('OK Game', $result->prices[0]->getGame()->getName());
    }

    public function testImportNextBatchUpdatesExistingPriceRecordForSameDayInsteadOfCreatingNew(): void
    {
        $game = new Game('Existing Game', 'existing-game');
        $existingPrice = new GamePrice($game, new \DateTimeImmutable('today'));
        $this->gamePriceRepository = $this->createMock(GamePriceRepository::class);
        $this->gamePriceRepository->method('findOneByGameAndDate')->willReturn($existingPrice);
        $this->service = $this->newService();

        $steamGame = self::makeSteamGame(1, 50, $game);
        $this->steamGameRepository->method('findBatchForPriceImport')->willReturn([$steamGame]);
        $this->steamClient->method('fetchAppDetailsForRussia')->willReturn(['is_free' => true]);

        $this->entityManager->expects($this->never())->method('persist');

        $result = $this->service->importNextBatch(5, 1000, 1000);

        self::assertSame($existingPrice, $result->prices[0]);
        self::assertTrue($existingPrice->isFree());
    }

    public function testImportNextBatchDelaysBetweenItemsUsingRandomDelayRangeValue(): void
    {
        $game1 = self::makeSteamGame(1, 1, new Game('A', 'a'));
        $game2 = self::makeSteamGame(2, 2, new Game('B', 'b'));
        $game3 = self::makeSteamGame(3, 3, new Game('C', 'c'));
        $this->steamGameRepository->method('findBatchForPriceImport')->willReturn([$game1, $game2, $game3]);
        $this->steamClient->method('fetchAppDetailsForRussia')->willReturn(null);

        $this->randomDelayRange->expects($this->exactly(2))->method('next')->with(1000, 3000)->willReturn(1777);
        $this->rateLimiter->expects($this->exactly(2))->method('delay')->with(1777);

        $this->service->importNextBatch(3, 1000, 3000);
    }

    public function testImportNextBatchUsesPersistedCursorAsStartingPoint(): void
    {
        $cursor = (new SteamPriceImportCursor())->setPosition(500, 999);
        $this->cursorRepository = $this->createMock(SteamPriceImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn($cursor);
        $this->service = $this->newService();

        $steamGame = self::makeSteamGame(1000, 60, new Game('After Cursor', 'after-cursor'));
        $this->steamGameRepository->expects($this->once())->method('findBatchForPriceImport')
            ->with(500, 999, 5)
            ->willReturn([$steamGame]);
        $this->steamClient->method('fetchAppDetailsForRussia')->willReturn(null);

        $this->service->importNextBatch(5, 1000, 1000);
    }

    public function testImportNextBatchAdvancesCursorToLastProcessedGamePopularityAndId(): void
    {
        $cursor = new SteamPriceImportCursor();
        $this->cursorRepository = $this->createMock(SteamPriceImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn($cursor);
        $this->service = $this->newService();

        $gameB = (new Game('B', 'b'))->setPopularity(42);
        $game1 = self::makeSteamGame(10, 1, new Game('A', 'a'));
        $game2 = self::makeSteamGame(20, 2, $gameB);
        $this->steamGameRepository->method('findBatchForPriceImport')->willReturn([$game1, $game2]);
        $this->steamClient->method('fetchAppDetailsForRussia')->willReturn(null);

        $this->service->importNextBatch(5, 1000, 1000);

        self::assertSame(20, $cursor->getLastSteamGameId());
        self::assertSame(42, $cursor->getLastPopularity());
    }

    public function testImportNextBatchStoresPopularityAsMinusOneWhenLastGameHasNone(): void
    {
        $cursor = new SteamPriceImportCursor();
        $this->cursorRepository = $this->createMock(SteamPriceImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn($cursor);
        $this->service = $this->newService();

        $steamGame = self::makeSteamGame(1, 10, new Game('No Popularity Game', 'no-popularity-game'));
        $this->steamGameRepository->method('findBatchForPriceImport')->willReturn([$steamGame]);
        $this->steamClient->method('fetchAppDetailsForRussia')->willReturn(null);

        $this->service->importNextBatch(5, 1000, 1000);

        self::assertSame(-1, $cursor->getLastPopularity());
    }

    public function testImportNextBatchStopsWithoutWrappingWhenCatalogEndReachedMidDay(): void
    {
        $cursor = (new SteamPriceImportCursor())->setPosition(500, 999);
        $this->cursorRepository = $this->createMock(SteamPriceImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn($cursor);
        $this->service = $this->newService();

        $this->steamGameRepository->expects($this->once())->method('findBatchForPriceImport')
            ->with(500, 999, 5)
            ->willReturn([]);

        $result = $this->service->importNextBatch(5, 1000, 1000);

        self::assertSame([], $result->prices);
        self::assertFalse($result->startedNewDay);
        self::assertSame(999, $cursor->getLastSteamGameId());
        self::assertSame(500, $cursor->getLastPopularity());
    }

    public function testImportNextBatchResetsCursorOnFirstRunOfNewDay(): void
    {
        $cursor = (new SteamPriceImportCursor())->setPosition(500, 999);
        self::makeCursorUpdatedAtYesterday($cursor);
        $this->cursorRepository = $this->createMock(SteamPriceImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn($cursor);
        $this->service = $this->newService();

        $steamGame = self::makeSteamGame(5, 70, new Game('Most Popular Today', 'most-popular-today'));
        $this->steamGameRepository->expects($this->once())->method('findBatchForPriceImport')
            ->with(null, 0, 5)
            ->willReturn([$steamGame]);
        $this->steamClient->method('fetchAppDetailsForRussia')->willReturn(null);

        $result = $this->service->importNextBatch(5, 1000, 1000);

        self::assertTrue($result->startedNewDay);
        self::assertSame(5, $cursor->getLastSteamGameId());
    }

    public function testImportNextBatchReturnsEmptyResultWhenCatalogIsCompletelyEmpty(): void
    {
        $this->steamGameRepository->method('findBatchForPriceImport')->willReturn([]);

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');
        $this->rateLimiter->expects($this->never())->method('delay');

        $result = $this->service->importNextBatch(5, 1000, 1000);

        self::assertSame([], $result->prices);
        self::assertSame(0, $result->skippedCount);
        self::assertFalse($result->startedNewDay);
    }

    public function testImportPriceForGameReturnsPricedResult(): void
    {
        $game = new Game('Half-Life', 'half-life');
        $steamGame = self::makeSteamGame(1, 10, $game);
        $this->steamClient->method('fetchAppDetailsForRussia')->willReturn([
            'price_overview' => ['final' => 199900, 'currency' => 'RUB'],
        ]);

        $price = $this->service->importPriceForGame($steamGame);

        self::assertNotNull($price);
        self::assertTrue($price->isAvailableInRussia());
        self::assertSame(199900, $price->getPriceKopecks());
    }

    public function testImportPriceForGameReturnsFreeResult(): void
    {
        $steamGame = self::makeSteamGame(1, 20, new Game('Free Game', 'free-game'));
        $this->steamClient->method('fetchAppDetailsForRussia')->willReturn(['is_free' => true]);

        $price = $this->service->importPriceForGame($steamGame);

        self::assertNotNull($price);
        self::assertTrue($price->isFree());
        self::assertTrue($steamGame->isPriceFree());
    }

    public function testImportPriceForGameReturnsUnavailableResult(): void
    {
        $steamGame = self::makeSteamGame(1, 30, new Game('Locked Game', 'locked-game'));
        $this->steamClient->method('fetchAppDetailsForRussia')->willReturn(null);

        $price = $this->service->importPriceForGame($steamGame);

        self::assertNotNull($price);
        self::assertFalse($price->isAvailableInRussia());
        self::assertFalse($steamGame->isAvailableInRussia());
    }

    public function testImportPriceForGameReturnsNullOnSteamApiException(): void
    {
        $steamGame = self::makeSteamGame(1, 40, new Game('Flaky Game', 'flaky-game'));
        $this->steamClient->method('fetchAppDetailsForRussia')
            ->willThrowException(new SteamApiException('Ошибка сети'));

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $price = $this->service->importPriceForGame($steamGame);

        self::assertNull($price);
    }
}
