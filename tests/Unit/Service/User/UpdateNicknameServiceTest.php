<?php

namespace App\Tests\Unit\Service\User;

use App\Dto\User\UpdateNicknameRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\User\Exceptions\NicknameAlreadyTakenException;
use App\Service\User\UpdateNicknameService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** Мок UserRepository здесь используется только как стаб (findOneByNickname), без expects(). */
#[AllowMockObjectsWithoutExpectations]
class UpdateNicknameServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UserRepository&MockObject $userRepository;
    private UpdateNicknameService $service;
    private User $user;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->service = new UpdateNicknameService($this->entityManager, $this->userRepository);

        $this->user = new User('player@retrogame.local', 'hash');
    }

    private function makeRequest(string $nickname): UpdateNicknameRequest
    {
        $request = new UpdateNicknameRequest();
        $request->nickname = $nickname;

        return $request;
    }

    public function testUpdateSetsNicknameWhenFree(): void
    {
        $this->userRepository->method('findOneByNickname')->willReturn(null);
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->update($this->user, $this->makeRequest('PlayerOne'));

        self::assertSame('PlayerOne', $this->user->getNickname());
    }

    public function testUpdateTrimsWhitespace(): void
    {
        $this->userRepository->method('findOneByNickname')->willReturn(null);

        $this->service->update($this->user, $this->makeRequest('  PlayerOne  '));

        self::assertSame('PlayerOne', $this->user->getNickname());
    }

    public function testUpdateAllowsKeepingOwnCurrentNickname(): void
    {
        $this->user->setNickname('PlayerOne');
        $this->userRepository->method('findOneByNickname')->willReturn($this->user);
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->update($this->user, $this->makeRequest('PlayerOne'));

        self::assertSame('PlayerOne', $this->user->getNickname());
    }

    public function testUpdateThrowsWhenNicknameTakenByAnotherUser(): void
    {
        $reflection = new \ReflectionProperty($this->user, 'id');
        $reflection->setValue($this->user, 1);

        $other = new User('other@retrogame.local', 'hash');
        $otherReflection = new \ReflectionProperty($other, 'id');
        $otherReflection->setValue($other, 2);

        $this->userRepository->method('findOneByNickname')->willReturn($other);
        $this->entityManager->expects($this->never())->method('flush');

        $this->expectException(NicknameAlreadyTakenException::class);

        $this->service->update($this->user, $this->makeRequest('PlayerOne'));
    }
}
