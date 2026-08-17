<?php

namespace App\Tests\Unit\Service\User;

use App\Dto\User\UpdatePrivacyRequest;
use App\Entity\User;
use App\Service\User\UpdateProfilePrivacyService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UpdateProfilePrivacyServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UpdateProfilePrivacyService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->service = new UpdateProfilePrivacyService($this->entityManager);
    }

    public function testUpdateSetsProfileVisibilityAndFlushes(): void
    {
        $user = new User('player@retrogame.local', 'hash');
        $request = new UpdatePrivacyRequest();
        $request->isProfilePublic = true;

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->update($user, $request);

        self::assertTrue($user->isProfilePublic());
    }

    public function testUpdateCanCloseProfileAgain(): void
    {
        $user = (new User('player@retrogame.local', 'hash'))->setIsProfilePublic(true);
        $request = new UpdatePrivacyRequest();
        $request->isProfilePublic = false;

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->update($user, $request);

        self::assertFalse($user->isProfilePublic());
    }
}
