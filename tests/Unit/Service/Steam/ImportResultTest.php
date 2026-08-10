<?php

namespace App\Tests\Unit\Service\Steam;

use App\Entity\Enum\SteamGameStatus;
use App\Entity\Game;
use App\Entity\SteamGame;
use App\Service\Steam\ImportResult;
use PHPUnit\Framework\TestCase;

class ImportResultTest extends TestCase
{
    public function testCountByStatusCountsOnlyMatchingEntries(): void
    {
        $success1 = new SteamGame(1);
        $success1->setGame(new Game('A', 'a'));
        $success1->markSuccess(['name' => 'A']);

        $success2 = new SteamGame(2);
        $success2->setGame(new Game('B', 'b'));
        $success2->markSuccess(['name' => 'B']);

        $failed = new SteamGame(3);
        $failed->markFailure('boom');

        $result = new ImportResult(steamGames: [$success1, $success2, $failed]);

        self::assertSame(2, $result->countByStatus(SteamGameStatus::Success));
        self::assertSame(1, $result->countByStatus(SteamGameStatus::Failed));
        self::assertSame(0, $result->countByStatus(SteamGameStatus::Pending));
    }

    public function testCountByStatusReturnsZeroForEmptyList(): void
    {
        $result = new ImportResult(steamGames: []);

        self::assertSame(0, $result->countByStatus(SteamGameStatus::Success));
    }
}
