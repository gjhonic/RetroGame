<?php

namespace App\Tests\Unit\Service\User;

use App\Dto\User\ChangePasswordRequest;
use App\Entity\User;
use App\Service\User\ChangePasswordService;
use App\Service\User\Exceptions\InvalidCurrentPasswordException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ChangePasswordServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private ChangePasswordService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);

        $this->service = new ChangePasswordService($this->entityManager, $this->passwordHasher);
    }

    private function makeRequest(): ChangePasswordRequest
    {
        $request = new ChangePasswordRequest();
        $request->currentPassword = 'old-secret';
        $request->newPassword = 'new-secret123';

        return $request;
    }

    public function testChangePasswordHashesNewPasswordWhenCurrentIsValid(): void
    {
        $user = new User('player@retrogame.local', 'old-hash');
        $this->passwordHasher->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, 'old-secret')
            ->willReturn(true);
        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->with($user, 'new-secret123')
            ->willReturn('new-hash');
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->changePassword($user, $this->makeRequest());

        self::assertSame('new-hash', $user->getPassword());
    }

    public function testChangePasswordThrowsWhenCurrentPasswordIsInvalid(): void
    {
        $user = new User('player@retrogame.local', 'old-hash');
        $this->passwordHasher->expects($this->once())->method('isPasswordValid')->willReturn(false);
        $this->entityManager->expects($this->never())->method('flush');

        $this->expectException(InvalidCurrentPasswordException::class);

        $this->service->changePassword($user, $this->makeRequest());
    }
}
