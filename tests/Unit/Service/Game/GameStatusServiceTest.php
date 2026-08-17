<?php

namespace App\Tests\Unit\Service\Game;

use App\Entity\Enum\GamePlaythroughStatus;
use App\Entity\Game;
use App\Entity\GameStatus;
use App\Entity\User;
use App\Repository\GameStatusRepository;
use App\Service\Game\GameStatusService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** Мок GameStatusRepository здесь используется только как стаб (findOneByGameAndUser), без expects(). */
#[AllowMockObjectsWithoutExpectations]
class GameStatusServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private GameStatusRepository&MockObject $gameStatusRepository;
    private GameStatusService $service;
    private Game $game;
    private User $user;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->gameStatusRepository = $this->createMock(GameStatusRepository::class);
        $this->service = new GameStatusService($this->entityManager, $this->gameStatusRepository);

        $this->game = new Game('Half-Life', 'half-life');
        $this->user = new User('player@retrogame.local', 'hash');
    }

    public function testSetStatusCreatesNewStatusWhenNoneExists(): void
    {
        $this->gameStatusRepository->method('findOneByGameAndUser')->willReturn(null);
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $status = $this->service->setStatus($this->game, $this->user, GamePlaythroughStatus::Planned);

        self::assertSame(GamePlaythroughStatus::Planned, $status->getStatus());
    }

    public function testSetStatusChangesExistingStatus(): void
    {
        $status = new GameStatus($this->game, $this->user, GamePlaythroughStatus::Planned);
        $this->gameStatusRepository->method('findOneByGameAndUser')->willReturn($status);
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->setStatus($this->game, $this->user, GamePlaythroughStatus::Completed);

        self::assertSame($status, $result);
        self::assertSame(GamePlaythroughStatus::Completed, $result->getStatus());
    }

    public function testRemoveStatusRemovesExistingStatus(): void
    {
        $status = new GameStatus($this->game, $this->user, GamePlaythroughStatus::InProgress);
        $this->gameStatusRepository->method('findOneByGameAndUser')->willReturn($status);
        $this->entityManager->expects($this->once())->method('remove')->with($status);
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->removeStatus($this->game, $this->user);
    }

    public function testRemoveStatusIsNoOpWhenStatusDoesNotExist(): void
    {
        $this->gameStatusRepository->method('findOneByGameAndUser')->willReturn(null);
        $this->entityManager->expects($this->never())->method('remove');
        $this->entityManager->expects($this->never())->method('flush');

        $this->service->removeStatus($this->game, $this->user);
    }
}
