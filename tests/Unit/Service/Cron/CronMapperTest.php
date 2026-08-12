<?php

namespace App\Tests\Unit\Service\Cron;

use App\Entity\Cron;
use App\Entity\CronRun;
use App\Entity\Enum\CronRunStatus;
use App\Service\Cron\CronMapper;
use PHPUnit\Framework\TestCase;

class CronMapperTest extends TestCase
{
    private CronMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new CronMapper();
    }

    public function testToListItemWithoutLastRun(): void
    {
        $cron = new Cron('app:games:import');
        $cron->setColor('#198754');
        $cron->setName('Импорт игр из Steam');

        $item = $this->mapper->toListItem($cron, null);

        self::assertSame('app:games:import', $item['command']);
        self::assertSame('Импорт игр из Steam', $item['name']);
        self::assertSame('#198754', $item['color']);
        self::assertNull($item['lastRun']);
    }

    public function testToListItemWithLastRun(): void
    {
        $cron = new Cron('app:games:import');
        $run = new CronRun('app:games:import', null, null);
        $run->finish(CronRunStatus::Success, 0, 1024, null);

        $item = $this->mapper->toListItem($cron, $run);

        self::assertSame('success', $item['lastRun']['status']);
        self::assertSame($run->getStartedAt()->format(\DateTimeInterface::ATOM), $item['lastRun']['startedAt']);
    }

    public function testToDetail(): void
    {
        $cron = new Cron('app:games:import');

        $detail = $this->mapper->toDetail($cron);

        self::assertSame('app:games:import', $detail['command']);
        self::assertNull($detail['name']);
        self::assertNull($detail['color']);
        self::assertSame($cron->getCreatedAt()->format(\DateTimeInterface::ATOM), $detail['createdAt']);
    }
}
