<?php

namespace App\Tests\Unit\Service\User;

use App\Dto\User\RegisterUserRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\User\Exceptions\EmailAlreadyRegisteredException;
use App\Service\User\UserRegistrationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AllowMockObjectsWithoutExpectations]
class UserRegistrationServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UserRepository&MockObject $userRepository;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private UserRegistrationService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed-password');

        $this->service = new UserRegistrationService(
            $this->entityManager,
            $this->userRepository,
            $this->passwordHasher,
        );
    }

    private function makeRequest(): RegisterUserRequest
    {
        $request = new RegisterUserRequest();
        $request->email = 'player@retrogame.local';
        $request->password = 'secret123';
        $request->nickname = 'Player One';

        return $request;
    }

    public function testRegisterCreatesUserWithHashedPassword(): void
    {
        $this->userRepository->method('findOneByEmail')->willReturn(null);
        $this->entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(User::class));
        $this->entityManager->expects($this->once())->method('flush');

        $user = $this->service->register($this->makeRequest());

        self::assertSame('player@retrogame.local', $user->getEmail());
        self::assertSame('hashed-password', $user->getPassword());
        self::assertSame('Player One', $user->getNickname());
    }

    public function testRegisterThrowsWhenEmailAlreadyRegistered(): void
    {
        $this->userRepository->method('findOneByEmail')->willReturn(
            new User('player@retrogame.local', 'old-hash'),
        );
        $this->entityManager->expects($this->never())->method('persist');

        $this->expectException(EmailAlreadyRegisteredException::class);

        $this->service->register($this->makeRequest());
    }
}
