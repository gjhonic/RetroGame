<?php

namespace App\Tests\Unit\Service\Plati;

use App\Service\Plati\GameMatcher;
use App\Service\Plati\PlatiSearchItem;
use PHPUnit\Framework\TestCase;

class GameMatcherTest extends TestCase
{
    private GameMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new GameMatcher();
    }

    private static function item(
        string $name,
        int $cntSell,
        ?string $nameEng = null,
        string $url = 'https://plati.market/itm/1',
        string $sellerName = 'Seller',
    ): PlatiSearchItem {
        return new PlatiSearchItem(
            id: '1',
            name: $name,
            nameEng: $nameEng ?? $name,
            url: $url,
            cntSell: $cntSell,
            sellerName: $sellerName,
        );
    }

    public function testMatchingItemsKeepsOnlyItemsContainingGameNameInTitle(): void
    {
        $items = [
            self::item('Counter-Strike 2 Prime Status', 100),
            self::item('RUST (STEAM GIFT RU/CIS)', 100),
        ];

        $matches = $this->matcher->matchingItems('Rust', $items);

        self::assertCount(1, $matches);
        self::assertSame('RUST (STEAM GIFT RU/CIS)', $matches[0]->name);
    }

    public function testMatchingItemsIsApostropheInsensitive(): void
    {
        $matches = $this->matcher->matchingItems(
            "Garry's Mod",
            [self::item('Garrys Mod STEAM•RU ⚡️АВТОДОСТАВКА', 3143)],
        );

        self::assertCount(1, $matches);
    }

    public function testTopMatchesReturnsMatchesSortedByMostSoldDescending(): void
    {
        $items = [
            self::item('Half-Life STEAM Gift', 50, url: 'https://plati.market/itm/1'),
            self::item('Half-Life STEAM Автодоставка', 200, url: 'https://plati.market/itm/2'),
            self::item('Half-Life STEAM Ключ', 100, url: 'https://plati.market/itm/3'),
        ];

        $top = $this->matcher->topMatches('Half-Life', $items, 3);

        self::assertSame([200, 100, 50], array_map(static fn (PlatiSearchItem $i): int => $i->cntSell, $top));
    }

    public function testTopMatchesLimitsResultCount(): void
    {
        $items = [
            self::item('Half-Life STEAM Gift', 50),
            self::item('Half-Life STEAM Автодоставка', 200),
            self::item('Half-Life STEAM Ключ', 100),
        ];

        $top = $this->matcher->topMatches('Half-Life', $items, 2);

        self::assertCount(2, $top);
    }

    public function testTopMatchesReturnsEmptyArrayWhenNoItemsMatch(): void
    {
        $top = $this->matcher->topMatches('Very Specific Game Name', [
            self::item('Something Completely Different', 100),
        ], 3);

        self::assertSame([], $top);
    }

    public function testFindByUrlReturnsMatchingItem(): void
    {
        $items = [
            self::item('A', 10, url: 'https://plati.market/itm/1'),
            self::item('B', 20, url: 'https://plati.market/itm/2'),
        ];

        $found = $this->matcher->findByUrl($items, 'https://plati.market/itm/2');

        self::assertNotNull($found);
        self::assertSame('B', $found->name);
    }

    public function testFindByUrlReturnsNullWhenNoItemHasThisUrl(): void
    {
        $items = [self::item('A', 10, url: 'https://plati.market/itm/1')];

        self::assertNull($this->matcher->findByUrl($items, 'https://plati.market/itm/999'));
    }
}
