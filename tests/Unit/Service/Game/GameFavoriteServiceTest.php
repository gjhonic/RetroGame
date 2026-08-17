<?php

namespace App\Tests\Unit\Service\Game;

use App\Entity\Game;
use App\Entity\GameFavorite;
use App\Entity\User;
use App\Repository\GameFavoriteRepository;
use App\Service\Game\GameFavoriteService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** Мок GameFavoriteRepository здесь используется только как стаб (findOneByGameAndUser), без expects(). */
#[AllowMockObjectsWithoutExpectations]
class GameFavoriteServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private GameFavoriteRepository&MockObject $gameFavoriteRepository;
    private GameFavoriteService $service;
    private Game $game;
    private User $user;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->gameFavoriteRepository = $this->createMock(GameFavoriteRepository::class);
        $this->service = new GameFavoriteService($this->entityManager, $this->gameFavoriteRepository);

        $this->game = new Game('Half-Life', 'half-life');
        $this->user = new User('fan@retrogame.local', 'hash');
    }

    public function testAddFavoriteCreatesNewFavoriteWhenNoneExists(): void
    {
        $this->gameFavoriteRepository->method('findOneByGameAndUser')->willReturn(null);
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $favorite = $this->service->addFavorite($this->game, $this->user);

        self::assertSame($this->game, $favorite->getGame());
    }

    public function testAddFavoriteIsNoOpWhenAlreadyFavorited(): void
    {
        $favorite = new GameFavorite($this->game, $this->user);
        $this->gameFavoriteRepository->method('findOneByGameAndUser')->willReturn($favorite);
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $result = $this->service->addFavorite($this->game, $this->user);

        self::assertSame($favorite, $result);
    }

    public function testRemoveFavoriteRemovesExistingFavorite(): void
    {
        $favorite = new GameFavorite($this->game, $this->user);
        $this->gameFavoriteRepository->method('findOneByGameAndUser')->willReturn($favorite);
        $this->entityManager->expects($this->once())->method('remove')->with($favorite);
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->removeFavorite($this->game, $this->user);
    }

    public function testRemoveFavoriteIsNoOpWhenFavoriteDoesNotExist(): void
    {
        $this->gameFavoriteRepository->method('findOneByGameAndUser')->willReturn(null);
        $this->entityManager->expects($this->never())->method('remove');
        $this->entityManager->expects($this->never())->method('flush');

        $this->service->removeFavorite($this->game, $this->user);
    }
}
