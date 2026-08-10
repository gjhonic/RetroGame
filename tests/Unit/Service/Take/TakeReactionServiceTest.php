<?php

namespace App\Tests\Unit\Service\Take;

use App\Entity\Enum\TakeReactionType;
use App\Entity\Game;
use App\Entity\Take;
use App\Entity\TakeReaction;
use App\Entity\User;
use App\Repository\TakeReactionRepository;
use App\Service\Take\TakeReactionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** Мок TakeReactionRepository здесь используется только как стаб (findOneByTakeAndUser), без expects(). */
#[AllowMockObjectsWithoutExpectations]
class TakeReactionServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private TakeReactionRepository&MockObject $takeReactionRepository;
    private TakeReactionService $service;
    private Take $take;
    private User $user;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->takeReactionRepository = $this->createMock(TakeReactionRepository::class);
        $this->service = new TakeReactionService($this->entityManager, $this->takeReactionRepository);

        $this->take = new Take(new User('author@retrogame.local', 'hash'), new Game('Half-Life', 'half-life'), 'T');
        $this->user = new User('voter@retrogame.local', 'hash');
    }

    public function testSetReactionCreatesNewReactionWhenNoneExists(): void
    {
        $this->takeReactionRepository->method('findOneByTakeAndUser')->willReturn(null);
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $reaction = $this->service->setReaction($this->take, $this->user, TakeReactionType::Like);

        self::assertSame(TakeReactionType::Like, $reaction->getType());
    }

    public function testSetReactionChangesTypeOfExistingReaction(): void
    {
        $reaction = new TakeReaction($this->take, $this->user, TakeReactionType::Like);
        $this->takeReactionRepository->method('findOneByTakeAndUser')->willReturn($reaction);
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->setReaction($this->take, $this->user, TakeReactionType::Dislike);

        self::assertSame($reaction, $result);
        self::assertSame(TakeReactionType::Dislike, $result->getType());
    }

    public function testRemoveReactionRemovesExistingReaction(): void
    {
        $reaction = new TakeReaction($this->take, $this->user, TakeReactionType::Like);
        $this->takeReactionRepository->method('findOneByTakeAndUser')->willReturn($reaction);
        $this->entityManager->expects($this->once())->method('remove')->with($reaction);
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->removeReaction($this->take, $this->user);
    }

    public function testRemoveReactionIsNoOpWhenReactionDoesNotExist(): void
    {
        $this->takeReactionRepository->method('findOneByTakeAndUser')->willReturn(null);
        $this->entityManager->expects($this->never())->method('remove');
        $this->entityManager->expects($this->never())->method('flush');

        $this->service->removeReaction($this->take, $this->user);
    }
}
