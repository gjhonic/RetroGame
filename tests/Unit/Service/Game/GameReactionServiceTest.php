<?php

namespace App\Tests\Unit\Service\Game;

use App\Entity\Enum\GameReactionType;
use App\Entity\Game;
use App\Entity\GameReaction;
use App\Entity\User;
use App\Repository\GameReactionRepository;
use App\Service\Game\GameReactionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** Мок GameReactionRepository здесь используется только как стаб (findOneByGameAndUser), без expects(). */
#[AllowMockObjectsWithoutExpectations]
class GameReactionServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private GameReactionRepository&MockObject $gameReactionRepository;
    private GameReactionService $service;
    private Game $game;
    private User $user;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->gameReactionRepository = $this->createMock(GameReactionRepository::class);
        $this->service = new GameReactionService($this->entityManager, $this->gameReactionRepository);

        $this->game = new Game('Half-Life', 'half-life');
        $this->user = new User('voter@retrogame.local', 'hash');
    }

    public function testSetReactionCreatesNewReactionWhenNoneExists(): void
    {
        $this->gameReactionRepository->method('findOneByGameAndUser')->willReturn(null);
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $reaction = $this->service->setReaction($this->game, $this->user, GameReactionType::Like);

        self::assertSame(GameReactionType::Like, $reaction->getType());
    }

    public function testSetReactionChangesTypeOfExistingReaction(): void
    {
        $reaction = new GameReaction($this->game, $this->user, GameReactionType::Like);
        $this->gameReactionRepository->method('findOneByGameAndUser')->willReturn($reaction);
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->setReaction($this->game, $this->user, GameReactionType::Dislike);

        self::assertSame($reaction, $result);
        self::assertSame(GameReactionType::Dislike, $result->getType());
    }

    public function testRemoveReactionRemovesExistingReaction(): void
    {
        $reaction = new GameReaction($this->game, $this->user, GameReactionType::Like);
        $this->gameReactionRepository->method('findOneByGameAndUser')->willReturn($reaction);
        $this->entityManager->expects($this->once())->method('remove')->with($reaction);
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->removeReaction($this->game, $this->user);
    }

    public function testRemoveReactionIsNoOpWhenReactionDoesNotExist(): void
    {
        $this->gameReactionRepository->method('findOneByGameAndUser')->willReturn(null);
        $this->entityManager->expects($this->never())->method('remove');
        $this->entityManager->expects($this->never())->method('flush');

        $this->service->removeReaction($this->game, $this->user);
    }
}
