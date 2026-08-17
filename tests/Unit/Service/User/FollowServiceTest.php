<?php

namespace App\Tests\Unit\Service\User;

use App\Entity\User;
use App\Entity\UserFollow;
use App\Repository\UserFollowRepository;
use App\Service\User\Exceptions\CannotFollowSelfException;
use App\Service\User\FollowService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** Мок UserFollowRepository здесь используется только как стаб (findOneByFollowerAndFollowed), без expects(). */
#[AllowMockObjectsWithoutExpectations]
class FollowServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UserFollowRepository&MockObject $userFollowRepository;
    private FollowService $service;
    private User $follower;
    private User $followed;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userFollowRepository = $this->createMock(UserFollowRepository::class);
        $this->service = new FollowService($this->entityManager, $this->userFollowRepository);

        $this->follower = new User('follower@retrogame.local', 'hash');
        $this->followed = new User('followed@retrogame.local', 'hash');
    }

    public function testFollowCreatesNewFollowWhenNoneExists(): void
    {
        $this->userFollowRepository->method('findOneByFollowerAndFollowed')->willReturn(null);
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->follow($this->follower, $this->followed);
    }

    public function testFollowIsNoOpWhenAlreadyFollowing(): void
    {
        $follow = new UserFollow($this->follower, $this->followed);
        $this->userFollowRepository->method('findOneByFollowerAndFollowed')->willReturn($follow);
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $this->service->follow($this->follower, $this->followed);
    }

    public function testFollowThrowsWhenFollowingSelf(): void
    {
        $reflection = new \ReflectionProperty($this->follower, 'id');
        $reflection->setValue($this->follower, 1);
        $this->entityManager->expects($this->never())->method('persist');

        $this->expectException(CannotFollowSelfException::class);

        $this->service->follow($this->follower, $this->follower);
    }

    public function testUnfollowRemovesExistingFollow(): void
    {
        $follow = new UserFollow($this->follower, $this->followed);
        $this->userFollowRepository->method('findOneByFollowerAndFollowed')->willReturn($follow);
        $this->entityManager->expects($this->once())->method('remove')->with($follow);
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->unfollow($this->follower, $this->followed);
    }

    public function testUnfollowIsNoOpWhenFollowDoesNotExist(): void
    {
        $this->userFollowRepository->method('findOneByFollowerAndFollowed')->willReturn(null);
        $this->entityManager->expects($this->never())->method('remove');
        $this->entityManager->expects($this->never())->method('flush');

        $this->service->unfollow($this->follower, $this->followed);
    }
}
