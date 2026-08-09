<?php

namespace App\Tests\Unit\Service\Cron;

use App\Entity\CronRun;
use App\Service\Cron\CronLogReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class CronLogReaderTest extends TestCase
{
    private string $logDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->logDir = sys_get_temp_dir() . '/cron_log_reader_test_' . uniqid();
        $this->filesystem->mkdir($this->logDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->logDir);
    }

    public function testReadReturnsFileContentWhenLogExists(): void
    {
        $this->filesystem->mkdir($this->logDir . '/app_games_import');
        file_put_contents($this->logDir . '/app_games_import/1.log', 'строка лога');

        $run = new CronRun('app:games:import', null, 'app_games_import/1.log');
        $reader = new CronLogReader($this->logDir);

        self::assertSame('строка лога', $reader->read($run));
    }

    public function testReadReturnsNullWhenRunHasNoLogPath(): void
    {
        $run = new CronRun('app:games:import', null, null);
        $reader = new CronLogReader($this->logDir);

        self::assertNull($reader->read($run));
    }

    public function testReadReturnsNullWhenLogFileIsMissing(): void
    {
        $run = new CronRun('app:games:import', null, 'app_games_import/does-not-exist.log');
        $reader = new CronLogReader($this->logDir);

        self::assertNull($reader->read($run));
    }
}
