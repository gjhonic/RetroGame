<?php

namespace App\Tests\Unit\EventListener;

use App\Entity\CronRun;
use App\Entity\Enum\CronRunStatus;
use App\EventListener\CronTrackingListener;
use App\Repository\CronRunRepository;
use App\Tests\Unit\EventListener\Fixtures\TrackedFixtureCommand;
use App\Tests\Unit\EventListener\Fixtures\UntrackedFixtureCommand;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;

/**
 * EntityManager здесь и как стаб, и как мок проверки вызовов persist()/flush() —
 * строгая проверка "мок без expects()" отключена, как в других тестах проекта.
 * Вывод команд в тестах — BufferedOutput (не StreamOutput), поэтому реальное
 * tee-перехватывание потока в файл (TeeStreamFilter) здесь не задействуется —
 * оно проверено вручную прогоном настоящей команды (см. PR).
 */
#[AllowMockObjectsWithoutExpectations]
class CronTrackingListenerTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private CronRunRepository&MockObject $cronRunRepository;
    private CronTrackingListener $listener;
    private string $logDir;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->cronRunRepository = $this->createMock(CronRunRepository::class);
        $this->cronRunRepository->method('findStaleRunning')->willReturn([]);

        $this->logDir = sys_get_temp_dir() . '/cron_tracking_listener_test_' . uniqid();

        $this->listener = new CronTrackingListener(
            $this->entityManager,
            $this->cronRunRepository,
            new Filesystem(),
            $this->logDir,
        );
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->logDir);
    }

    public function testOnCommandCreatesAndPersistsRunForTrackedCommand(): void
    {
        $this->entityManager->expects($this->atLeastOnce())
            ->method('persist')
            ->with(self::isInstanceOf(CronRun::class));

        $this->listener->onCommand($this->commandEvent(new TrackedFixtureCommand()));

        self::assertSame('test:tracked', $this->currentRun()?->getCommand());
        self::assertDirectoryExists($this->logDir . '/test_tracked');
    }

    public function testOnCommandDoesNothingForUntrackedCommand(): void
    {
        $this->entityManager->expects($this->never())->method('persist');

        $this->listener->onCommand($this->commandEvent(new UntrackedFixtureCommand()));

        self::assertNull($this->currentRun());
    }

    public function testOnTerminateMarksRunAsSuccessOnZeroExitCode(): void
    {
        $this->listener->onCommand($this->commandEvent(new TrackedFixtureCommand()));
        $trackedRun = $this->currentRun();

        $this->listener->onTerminate($this->terminateEvent(new TrackedFixtureCommand(), 0));

        self::assertSame(CronRunStatus::Success, $trackedRun?->getStatus());
        self::assertNull($this->currentRun(), 'Слушатель должен сбросить текущий запуск после terminate');
    }

    public function testOnTerminateMarksRunAsFailedOnNonZeroExitCode(): void
    {
        $this->listener->onCommand($this->commandEvent(new TrackedFixtureCommand()));
        $trackedRun = $this->currentRun();

        $this->listener->onTerminate($this->terminateEvent(new TrackedFixtureCommand(), 1));

        self::assertSame(CronRunStatus::Failed, $trackedRun?->getStatus());
        self::assertSame(1, $trackedRun->getExitCode());
    }

    public function testOnTerminateUsesErrorMessageCapturedByOnError(): void
    {
        $command = new TrackedFixtureCommand();
        $this->listener->onCommand($this->commandEvent($command));
        $trackedRun = $this->currentRun();

        $this->listener->onError(new ConsoleErrorEvent(
            new ArrayInput([]),
            new BufferedOutput(),
            new \RuntimeException('Что-то пошло не так'),
            $command,
        ));
        $this->listener->onTerminate($this->terminateEvent($command, 1));

        self::assertSame('Что-то пошло не так', $trackedRun?->getErrorMessage());
    }

    public function testOnTerminateDoesNothingWithoutPriorCommand(): void
    {
        $this->entityManager->expects($this->never())->method('flush');

        $this->listener->onTerminate($this->terminateEvent(new TrackedFixtureCommand(), 0));
    }

    public function testOnCommandHealsStaleRunningEntries(): void
    {
        $staleRun = new CronRun('test:tracked', null, null);
        $this->cronRunRepository = $this->createMock(CronRunRepository::class);
        $this->cronRunRepository->method('findStaleRunning')->willReturn([$staleRun]);

        $this->listener = new CronTrackingListener(
            $this->entityManager,
            $this->cronRunRepository,
            new Filesystem(),
            $this->logDir,
        );

        $this->listener->onCommand($this->commandEvent(new TrackedFixtureCommand()));

        self::assertSame(CronRunStatus::Failed, $staleRun->getStatus());
    }

    private function commandEvent(Command $command): ConsoleCommandEvent
    {
        return new ConsoleCommandEvent($command, new ArrayInput([]), new BufferedOutput());
    }

    private function terminateEvent(Command $command, int $exitCode): ConsoleTerminateEvent
    {
        return new ConsoleTerminateEvent($command, new ArrayInput([]), new BufferedOutput(), $exitCode);
    }

    private function currentRun(): ?CronRun
    {
        $property = new \ReflectionProperty(CronTrackingListener::class, 'currentRun');

        return $property->getValue($this->listener);
    }
}
