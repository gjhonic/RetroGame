<?php

namespace App\Tests\Unit\Cron;

use App\Cron\CronDiscoveryService;
use App\Tests\Unit\EventListener\Fixtures\TrackedFixtureCommand;
use App\Tests\Unit\EventListener\Fixtures\UntrackedFixtureCommand;
use PHPUnit\Framework\TestCase;

class CronDiscoveryServiceTest extends TestCase
{
    public function testDiscoverTrackedCommandNamesReturnsOnlyCommandsWithAttribute(): void
    {
        $service = new CronDiscoveryService([new TrackedFixtureCommand(), new UntrackedFixtureCommand()]);

        self::assertSame(['test:tracked'], $service->discoverTrackedCommandNames());
    }

    public function testDiscoverTrackedCommandNamesReturnsEmptyArrayForNoCommands(): void
    {
        $service = new CronDiscoveryService([]);

        self::assertSame([], $service->discoverTrackedCommandNames());
    }
}
