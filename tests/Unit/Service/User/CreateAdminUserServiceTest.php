<?php

namespace App\Tests\Unit\Service\User;

use App\Entity\Enum\UserRole;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\User\CreateAdminUserService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AllowMockObjectsWithoutExpectations]
class CreateAdminUserServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UserRepository&MockObject $userRepository;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private CreateAdminUserService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed-password');

        $this->service = new CreateAdminUserService(
            $this->entityManager,
            $this->userRepository,
            $this->passwordHasher,
        );
    }

    public function testCreatesNewAdminWhenEmailNotFound(): void
    {
        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'admin@retrogame.local'])
            ->willReturn(null);
        $this->entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(User::class));
        $this->entityManager->expects($this->once())->method('flush');

        $user = $this->service->createOrUpdate('admin@retrogame.local', 'secret');

        self::assertSame('admin@retrogame.local', $user->getEmail());
        self::assertSame(UserRole::Admin, $user->getRole());
        self::assertSame('hashed-password', $user->getPassword());
    }

    public function testPromotesExistingUserToAdminWithoutCreatingNew(): void
    {
        $existingUser = new User('user@retrogame.local', 'old-hash', UserRole::User);
        $this->userRepository->method('findOneBy')->willReturn($existingUser);
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $user = $this->service->createOrUpdate('user@retrogame.local', 'new-secret');

        self::assertSame($existingUser, $user);
        self::assertSame(UserRole::Admin, $user->getRole());
        self::assertSame('hashed-password', $user->getPassword());
    }
}
