<?php

namespace App\Tests\Unit\Service\User;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\User\Exceptions\ProfileNotFoundException;
use App\Service\User\ProfileVisibilityService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Мок UserRepository здесь используется только как стаб (готовый ответ
 * findOneByNickname) — строгая проверка "мок без expects()" отключена, как
 * и в TakeApiControllerTest.
 */
#[AllowMockObjectsWithoutExpectations]
class ProfileVisibilityServiceTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private ProfileVisibilityService $service;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->service = new ProfileVisibilityService($this->userRepository);
    }

    public function testResolveReturnsUserWhenProfileIsPublic(): void
    {
        $owner = (new User('owner@retrogame.local', 'hash'))->setNickname('owner')->setIsProfilePublic(true);
        $this->userRepository->method('findOneByNickname')->willReturn($owner);

        $resolved = $this->service->resolveVisibleUser('owner', null);

        self::assertSame($owner, $resolved);
    }

    public function testResolveReturnsUserForOwnerEvenWhenProfileIsPrivate(): void
    {
        $owner = (new User('owner@retrogame.local', 'hash'))->setNickname('owner')->setIsProfilePublic(false);
        $this->setUserId($owner, 1);
        $this->userRepository->method('findOneByNickname')->willReturn($owner);

        $resolved = $this->service->resolveVisibleUser('owner', $owner);

        self::assertSame($owner, $resolved);
    }

    public function testResolveThrowsWhenProfileIsPrivateAndViewerIsSomeoneElse(): void
    {
        $owner = (new User('owner@retrogame.local', 'hash'))->setNickname('owner')->setIsProfilePublic(false);
        $viewer = new User('viewer@retrogame.local', 'hash');
        $this->setUserId($owner, 1);
        $this->setUserId($viewer, 2);
        $this->userRepository->method('findOneByNickname')->willReturn($owner);

        $this->expectException(ProfileNotFoundException::class);

        $this->service->resolveVisibleUser('owner', $viewer);
    }

    public function testResolveThrowsWhenProfileIsPrivateAndViewerIsAnonymous(): void
    {
        $owner = (new User('owner@retrogame.local', 'hash'))->setNickname('owner')->setIsProfilePublic(false);
        $this->userRepository->method('findOneByNickname')->willReturn($owner);

        $this->expectException(ProfileNotFoundException::class);

        $this->service->resolveVisibleUser('owner', null);
    }

    public function testResolveThrowsWhenNicknameNotFound(): void
    {
        $this->userRepository->method('findOneByNickname')->willReturn(null);

        $this->expectException(ProfileNotFoundException::class);

        $this->service->resolveVisibleUser('unknown', null);
    }

    private function setUserId(User $user, int $id): void
    {
        $reflection = new \ReflectionProperty($user, 'id');
        $reflection->setValue($user, $id);
    }
}
