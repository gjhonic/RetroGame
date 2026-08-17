<?php

namespace App\Tests\Unit\Controller\Api\Cabinet;

use App\Controller\Api\Cabinet\UserFollowApiController;
use App\Entity\User;
use App\Repository\UserFollowRepository;
use App\Repository\UserRepository;
use App\Service\User\Exceptions\CannotFollowSelfException;
use App\Service\User\FollowService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Мок UserRepository/FollowService здесь и как стаб (готовые ответы), и как мок
 * (проверка вызовов) — строгая проверка "мок без expects()" отключена, как
 * и в TakeApiControllerTest.
 */
#[AllowMockObjectsWithoutExpectations]
class UserFollowApiControllerTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private UserFollowRepository&MockObject $userFollowRepository;
    private FollowService&MockObject $followService;
    private UserFollowApiController $controller;
    private User $viewer;
    private User $target;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->userFollowRepository = $this->createMock(UserFollowRepository::class);
        $this->followService = $this->createMock(FollowService::class);

        $this->controller = new UserFollowApiController();
        $this->controller->setContainer(new Container());

        $this->viewer = new User('viewer@retrogame.local', 'hash');
        $this->target = (new User('target@retrogame.local', 'hash'))->setNickname('target');
    }

    public function testFollowReturnsUpdatedFollowStateAndCount(): void
    {
        $this->userRepository->method('findOneByNickname')->willReturn($this->target);
        $this->followService->expects($this->once())->method('follow')->with($this->viewer, $this->target);
        $this->userFollowRepository->method('countFollowers')->willReturn(3);

        $response = $this->controller->follow(
            'target',
            $this->userRepository,
            $this->userFollowRepository,
            $this->followService,
            $this->viewer,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertTrue($data['isFollowing']);
        self::assertSame(3, $data['followersCount']);
    }

    public function testFollowThrowsNotFoundExceptionForUnknownNickname(): void
    {
        $this->userRepository->method('findOneByNickname')->willReturn(null);
        $this->followService->expects($this->never())->method('follow');

        $this->expectException(NotFoundHttpException::class);

        $this->controller->follow(
            'unknown',
            $this->userRepository,
            $this->userFollowRepository,
            $this->followService,
            $this->viewer,
        );
    }

    public function testFollowReturnsBadRequestWhenFollowingSelf(): void
    {
        $this->userRepository->method('findOneByNickname')->willReturn($this->viewer);
        $this->followService->method('follow')
            ->willThrowException(new CannotFollowSelfException('Нельзя подписаться на самого себя.'));

        $response = $this->controller->follow(
            'viewer',
            $this->userRepository,
            $this->userFollowRepository,
            $this->followService,
            $this->viewer,
        );

        self::assertSame(400, $response->getStatusCode());
    }

    public function testUnfollowReturnsUpdatedFollowStateAndCount(): void
    {
        $this->userRepository->method('findOneByNickname')->willReturn($this->target);
        $this->followService->expects($this->once())->method('unfollow')->with($this->viewer, $this->target);
        $this->userFollowRepository->method('countFollowers')->willReturn(2);

        $response = $this->controller->unfollow(
            'target',
            $this->userRepository,
            $this->userFollowRepository,
            $this->followService,
            $this->viewer,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertFalse($data['isFollowing']);
        self::assertSame(2, $data['followersCount']);
    }

    public function testUnfollowThrowsNotFoundExceptionForUnknownNickname(): void
    {
        $this->userRepository->method('findOneByNickname')->willReturn(null);
        $this->followService->expects($this->never())->method('unfollow');

        $this->expectException(NotFoundHttpException::class);

        $this->controller->unfollow(
            'unknown',
            $this->userRepository,
            $this->userFollowRepository,
            $this->followService,
            $this->viewer,
        );
    }
}
