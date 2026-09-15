<?php

namespace App\Tests\Unit\Service\Steam;

use App\Entity\Game;
use App\Entity\GamePrice;
use App\Service\Steam\PriceImportResult;
use PHPUnit\Framework\TestCase;

class PriceImportResultTest extends TestCase
{
    public function testCountersCountOnlyMatchingEntries(): void
    {
        $today = new \DateTimeImmutable('today');

        $free = new GamePrice(new Game('Free Game', 'free-game'), $today);
        $free->markFree();

        $unavailable = new GamePrice(new Game('Locked Game', 'locked-game'), $today);
        $unavailable->markUnavailable();

        $priced = new GamePrice(new Game('Priced Game', 'priced-game'), $today);
        $priced->markPriced(199900, 'RUB');

        $result = new PriceImportResult(prices: [$free, $unavailable, $priced]);

        self::assertSame(1, $result->countFree());
        self::assertSame(1, $result->countUnavailable());
        self::assertSame(1, $result->countPriced());
    }

    public function testCountersReturnZeroForEmptyList(): void
    {
        $result = new PriceImportResult(prices: []);

        self::assertSame(0, $result->countFree());
        self::assertSame(0, $result->countUnavailable());
        self::assertSame(0, $result->countPriced());
    }
}
