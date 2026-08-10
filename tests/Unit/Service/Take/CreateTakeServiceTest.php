<?php

namespace App\Tests\Unit\Service\Take;

use App\Dto\Take\CreateTakeRequest;
use App\Entity\Game;
use App\Entity\User;
use App\Repository\GameRepository;
use App\Service\Take\CreateTakeService;
use App\Service\Take\Exceptions\GameNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** Мок GameRepository здесь используется только как стаб (find), без expects(). */
#[AllowMockObjectsWithoutExpectations]
class CreateTakeServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private GameRepository&MockObject $gameRepository;
    private CreateTakeService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->gameRepository = $this->createMock(GameRepository::class);

        $this->service = new CreateTakeService($this->entityManager, $this->gameRepository);
    }

    private function makeRequest(int $gameId = 1): CreateTakeRequest
    {
        $request = new CreateTakeRequest();
        $request->gameId = $gameId;
        $request->text = 'Great game, still holds up.';

        return $request;
    }

    public function testCreateSavesTakeWhenGameExists(): void
    {
        $game = new Game('Half-Life', 'half-life');
        $this->gameRepository->method('find')->willReturn($game);
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $user = new User('player@retrogame.local', 'hash');
        $take = $this->service->create($user, $this->makeRequest());

        self::assertSame($user, $take->getAuthor());
        self::assertSame($game, $take->getGame());
        self::assertSame('Great game, still holds up.', $take->getText());
    }

    public function testCreateThrowsWhenGameNotFound(): void
    {
        $this->gameRepository->method('find')->willReturn(null);
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $this->expectException(GameNotFoundException::class);

        $this->service->create(new User('player@retrogame.local', 'hash'), $this->makeRequest());
    }
}
