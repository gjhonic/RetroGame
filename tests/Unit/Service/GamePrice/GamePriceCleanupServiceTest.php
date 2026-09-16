<?php

namespace App\Tests\Unit\Service\GamePrice;

use App\Entity\Game;
use App\Entity\GamePrice;
use App\Repository\GamePriceRepository;
use App\Service\GamePrice\GamePriceCleanupService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * gamePriceRepository используется только как стаб (findAllInRangeOrderedByGame),
 * а entityManager — и как стаб, и как мок (проверка remove/flush).
 */
#[AllowMockObjectsWithoutExpectations]
class GamePriceCleanupServiceTest extends TestCase
{
    private GamePriceRepository&MockObject $gamePriceRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private GamePriceCleanupService $service;

    protected function setUp(): void
    {
        $this->gamePriceRepository = $this->createMock(GamePriceRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->service = new GamePriceCleanupService($this->gamePriceRepository, $this->entityManager);
    }

    /** Создаёт игру с заданным id (обычно проставляется Doctrine при persist) — нужен для группировки цен по игре. */
    private static function makeGame(int $id): Game
    {
        $game = new Game('Game ' . $id, 'game-' . $id);
        (new \ReflectionProperty($game, 'id'))->setValue($game, $id);

        return $game;
    }

    private static function makePrice(Game $game, string $date, ?int $priceKopecks): GamePrice
    {
        $price = new GamePrice($game, new \DateTimeImmutable($date));
        if ($priceKopecks === null) {
            $price->markUnavailable();
        } elseif ($priceKopecks === 0) {
            $price->markFree();
        } else {
            $price->markPriced($priceKopecks);
        }

        return $price;
    }

    public function testCleanupRemovesMiddleRecordsOfUnchangedPriceRunKeepingFirstAndLast(): void
    {
        $game = self::makeGame(1);
        $day1 = self::makePrice($game, '2026-09-01', 100);
        $day2 = self::makePrice($game, '2026-09-02', 100);
        $day3 = self::makePrice($game, '2026-09-03', 100);
        $day4 = self::makePrice($game, '2026-09-04', 100);
        $this->gamePriceRepository->method('findAllInRangeOrderedByGame')->willReturn([$day1, $day2, $day3, $day4]);

        $this->entityManager->expects($this->exactly(2))->method('remove')->with(
            $this->logicalOr($this->identicalTo($day2), $this->identicalTo($day3)),
        );
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->cleanup(2);

        self::assertSame(2, $result->deletedCount);
        self::assertSame(1, $result->processedGamesCount);
    }

    public function testCleanupKeepsBoundaryRecordsOnPriceChange(): void
    {
        $game = self::makeGame(1);
        $before = self::makePrice($game, '2026-09-01', 100);
        $after = self::makePrice($game, '2026-09-02', 200);
        $this->gamePriceRepository->method('findAllInRangeOrderedByGame')->willReturn([$before, $after]);

        $this->entityManager->expects($this->never())->method('remove');

        $result = $this->service->cleanup(2);

        self::assertSame(0, $result->deletedCount);
        self::assertSame(1, $result->processedGamesCount);
    }

    public function testCleanupKeepsBoundariesOfEachRunAroundPriceChangeInLongerHistory(): void
    {
        $game = self::makeGame(1);
        $prices = [
            self::makePrice($game, '2026-09-01', 100),
            self::makePrice($game, '2026-09-02', 100),
            self::makePrice($game, '2026-09-03', 100),
            self::makePrice($game, '2026-09-04', 200),
            self::makePrice($game, '2026-09-05', 200),
            self::makePrice($game, '2026-09-06', 200),
        ];
        $this->gamePriceRepository->method('findAllInRangeOrderedByGame')->willReturn($prices);

        $removed = [];
        $this->entityManager->method('remove')->willReturnCallback(function (GamePrice $price) use (&$removed): void {
            $removed[] = $price;
        });

        $result = $this->service->cleanup(2);

        self::assertSame(2, $result->deletedCount);
        self::assertSame([$prices[1], $prices[4]], $removed);
    }

    public function testCleanupProcessesEachGameIndependently(): void
    {
        $gameA = self::makeGame(1);
        $gameB = self::makeGame(2);
        $prices = [
            self::makePrice($gameA, '2026-09-01', 100),
            self::makePrice($gameA, '2026-09-02', 100),
            self::makePrice($gameA, '2026-09-03', 100),
            self::makePrice($gameB, '2026-09-01', 50),
            self::makePrice($gameB, '2026-09-02', 60),
        ];
        $this->gamePriceRepository->method('findAllInRangeOrderedByGame')->willReturn($prices);

        $result = $this->service->cleanup(2);

        self::assertSame(1, $result->deletedCount);
        self::assertSame(2, $result->processedGamesCount);
    }

    public function testCleanupTreatsUnavailableInRussiaAsRegularPriceValueForGrouping(): void
    {
        $game = self::makeGame(1);
        $prices = [
            self::makePrice($game, '2026-09-01', null),
            self::makePrice($game, '2026-09-02', null),
            self::makePrice($game, '2026-09-03', null),
        ];
        $this->gamePriceRepository->method('findAllInRangeOrderedByGame')->willReturn($prices);

        $this->entityManager->expects($this->once())->method('remove')->with($this->identicalTo($prices[1]));

        $result = $this->service->cleanup(2);

        self::assertSame(1, $result->deletedCount);
    }

    public function testCleanupReturnsZeroResultWhenNothingInRange(): void
    {
        $this->gamePriceRepository->method('findAllInRangeOrderedByGame')->willReturn([]);

        $this->entityManager->expects($this->never())->method('remove');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->cleanup(2);

        self::assertSame(0, $result->deletedCount);
        self::assertSame(0, $result->processedGamesCount);
    }

    public function testCleanupPassesComputedFromDateBasedOnWeeksAndNow(): void
    {
        $now = new \DateTimeImmutable('2026-09-16');

        $this->gamePriceRepository->expects($this->once())->method('findAllInRangeOrderedByGame')
            ->with(
                $this->equalTo(new \DateTimeImmutable('2026-09-02')),
                $this->equalTo($now),
            )
            ->willReturn([]);

        $this->service->cleanup(2, $now);
    }
}
