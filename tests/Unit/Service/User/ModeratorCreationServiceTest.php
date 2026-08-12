<?php

namespace App\Tests\Unit\Service\User;

use App\Dto\User\CreateModeratorRequest;
use App\Entity\Enum\UserRole;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\User\Exceptions\EmailAlreadyRegisteredException;
use App\Service\User\ModeratorCreationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AllowMockObjectsWithoutExpectations]
class ModeratorCreationServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UserRepository&MockObject $userRepository;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private ModeratorCreationService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed-password');

        $this->service = new ModeratorCreationService(
            $this->entityManager,
            $this->userRepository,
            $this->passwordHasher,
        );
    }

    private function makeRequest(): CreateModeratorRequest
    {
        $request = new CreateModeratorRequest();
        $request->email = 'moderator@retrogame.local';
        $request->password = 'secret123';
        $request->nickname = 'Moderator One';

        return $request;
    }

    public function testCreateCreatesUserWithModeratorRoleAndHashedPassword(): void
    {
        $this->userRepository->method('findOneByEmail')->willReturn(null);
        $this->entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(User::class));
        $this->entityManager->expects($this->once())->method('flush');

        $user = $this->service->create($this->makeRequest());

        self::assertSame('moderator@retrogame.local', $user->getEmail());
        self::assertSame('hashed-password', $user->getPassword());
        self::assertSame('Moderator One', $user->getNickname());
        self::assertSame(UserRole::Moderator, $user->getRole());
    }

    public function testCreateThrowsWhenEmailAlreadyRegistered(): void
    {
        $this->userRepository->method('findOneByEmail')->willReturn(
            new User('moderator@retrogame.local', 'old-hash'),
        );
        $this->entityManager->expects($this->never())->method('persist');

        $this->expectException(EmailAlreadyRegisteredException::class);

        $this->service->create($this->makeRequest());
    }
}
