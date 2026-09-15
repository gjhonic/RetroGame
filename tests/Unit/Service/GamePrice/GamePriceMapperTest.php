<?php

namespace App\Tests\Unit\Service\GamePrice;

use App\Entity\Game;
use App\Entity\GamePrice;
use App\Service\GamePrice\GamePriceMapper;
use PHPUnit\Framework\TestCase;

class GamePriceMapperTest extends TestCase
{
    public function testToApiIncludesSteamStoreAndUrlWhenAppIdGiven(): void
    {
        $game = new Game('Half-Life', 'half-life');
        $price = (new GamePrice($game, new \DateTimeImmutable('2026-09-15')))->markPriced(199900, 'RUB');

        $data = (new GamePriceMapper())->toApi($price, 70);

        self::assertSame('Steam', $data['store']);
        self::assertSame('https://store.steampowered.com/app/70/', $data['storeUrl']);
        self::assertSame(199900, $data['priceKopecks']);
        self::assertSame('RUB', $data['currency']);
        self::assertSame('2026-09-15', $data['date']);
    }

    public function testToApiLeavesStoreAndUrlNullWithoutAppId(): void
    {
        $game = new Game('Our Own Game', 'our-own-game');
        $price = (new GamePrice($game, new \DateTimeImmutable('2026-09-15')))->markFree();

        $data = (new GamePriceMapper())->toApi($price);

        self::assertNull($data['store']);
        self::assertNull($data['storeUrl']);
        self::assertTrue($data['isFree']);
    }
}
