<?php

namespace App\Tests\Unit\Controller\Api\Public;

use App\Controller\Api\Public\ProfileApiController;
use App\Entity\Enum\GamePlaythroughStatus;
use App\Entity\Game;
use App\Entity\GameFavorite;
use App\Entity\GameStatus;
use App\Entity\User;
use App\Entity\UserFollow;
use App\Repository\GameFavoriteRepository;
use App\Repository\GameStatusRepository;
use App\Repository\UserFollowRepository;
use App\Service\Game\GameMapper;
use App\Service\User\Exceptions\ProfileNotFoundException;
use App\Service\User\ProfileVisibilityService;
use App\Service\User\UserMapper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Мок ProfileVisibilityService/репозиториев здесь и как стаб (готовые ответы),
 * и как мок (проверка аргументов) — строгая проверка "мок без expects()"
 * отключена, как и в TakeApiControllerTest.
 */
#[AllowMockObjectsWithoutExpectations]
class ProfileApiControllerTest extends TestCase
{
    private ProfileVisibilityService&MockObject $profileVisibilityService;
    private GameFavoriteRepository&MockObject $gameFavoriteRepository;
    private GameStatusRepository&MockObject $gameStatusRepository;
    private UserFollowRepository&MockObject $userFollowRepository;
    private ProfileApiController $controller;
    private User $owner;

    protected function setUp(): void
    {
        $this->profileVisibilityService = $this->createMock(ProfileVisibilityService::class);
        $this->gameFavoriteRepository = $this->createMock(GameFavoriteRepository::class);
        $this->gameStatusRepository = $this->createMock(GameStatusRepository::class);
        $this->userFollowRepository = $this->createMock(UserFollowRepository::class);
        $this->owner = (new User('owner@retrogame.local', 'hash'))->setNickname('owner');

        $this->controller = new ProfileApiController();
        $this->controller->setContainer(new Container());
    }

    public function testShowReturnsPublicProfileForVisibleUser(): void
    {
        $this->profileVisibilityService->method('resolveVisibleUser')->willReturn($this->owner);
        $this->userFollowRepository->method('countFollowers')->willReturn(5);
        $this->userFollowRepository->method('countFollowing')->willReturn(2);

        $response = $this->controller->show(
            'owner',
            $this->profileVisibilityService,
            $this->userFollowRepository,
            new UserMapper(),
            null,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame('owner', $data['nickname']);
        self::assertSame(5, $data['followersCount']);
        self::assertSame(2, $data['followingCount']);
        self::assertFalse($data['isOwnProfile']);
        self::assertNull($data['isFollowing']);
        self::assertArrayNotHasKey('email', $data);
    }

    public function testShowThrowsNotFoundExceptionWhenProfileIsNotVisible(): void
    {
        $this->profileVisibilityService->method('resolveVisibleUser')
            ->willThrowException(new ProfileNotFoundException('Профиль не найден.'));

        $this->expectException(NotFoundHttpException::class);

        $this->controller->show(
            'hidden',
            $this->profileVisibilityService,
            $this->userFollowRepository,
            new UserMapper(),
            null,
        );
    }

    public function testShowMarksOwnProfileAndDoesNotExposeFollowState(): void
    {
        $this->setUserId($this->owner, 1);
        $this->profileVisibilityService->method('resolveVisibleUser')->willReturn($this->owner);
        $this->userFollowRepository->method('countFollowers')->willReturn(5);
        $this->userFollowRepository->expects($this->never())->method('findOneByFollowerAndFollowed');

        $response = $this->controller->show(
            'owner',
            $this->profileVisibilityService,
            $this->userFollowRepository,
            new UserMapper(),
            $this->owner,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertTrue($data['isOwnProfile']);
        self::assertNull($data['isFollowing']);
    }

    public function testShowIncludesIsFollowingForAuthorizedViewer(): void
    {
        $viewer = new User('viewer@retrogame.local', 'hash');

        $this->profileVisibilityService->method('resolveVisibleUser')->willReturn($this->owner);
        $this->userFollowRepository->method('countFollowers')->willReturn(5);
        $this->userFollowRepository->expects($this->once())
            ->method('findOneByFollowerAndFollowed')
            ->with($viewer, $this->owner)
            ->willReturn(null);

        $response = $this->controller->show(
            'owner',
            $this->profileVisibilityService,
            $this->userFollowRepository,
            new UserMapper(),
            $viewer,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertFalse($data['isOwnProfile']);
        self::assertFalse($data['isFollowing']);
    }

    public function testFollowersReturnsPageOfFollowerSummaries(): void
    {
        $follower = (new User('follower@retrogame.local', 'hash'))->setNickname('follower1');
        $follow = new UserFollow($follower, $this->owner);

        $this->profileVisibilityService->method('resolveVisibleUser')->willReturn($this->owner);
        $this->userFollowRepository->method('countFollowers')->willReturn(1);
        $this->userFollowRepository->expects($this->once())
            ->method('findFollowers')
            ->with($this->owner, 24, 0)
            ->willReturn([$follow]);

        $response = $this->controller->followers(
            'owner',
            new Request(),
            $this->profileVisibilityService,
            $this->userFollowRepository,
            new UserMapper(),
            null,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['total']);
        self::assertSame('follower1', $data['items'][0]['nickname']);
        self::assertArrayNotHasKey('email', $data['items'][0]);
    }

    public function testFollowersThrowsNotFoundExceptionWhenProfileIsNotVisible(): void
    {
        $this->profileVisibilityService->method('resolveVisibleUser')
            ->willThrowException(new ProfileNotFoundException('Профиль не найден.'));
        $this->userFollowRepository->expects($this->never())->method('findFollowers');

        $this->expectException(NotFoundHttpException::class);

        $this->controller->followers(
            'hidden',
            new Request(),
            $this->profileVisibilityService,
            $this->userFollowRepository,
            new UserMapper(),
            null,
        );
    }

    public function testFollowingReturnsPageOfFollowedSummaries(): void
    {
        $followed = (new User('followed@retrogame.local', 'hash'))->setNickname('followed1');
        $follow = new UserFollow($this->owner, $followed);

        $this->profileVisibilityService->method('resolveVisibleUser')->willReturn($this->owner);
        $this->userFollowRepository->method('countFollowing')->willReturn(1);
        $this->userFollowRepository->expects($this->once())
            ->method('findFollowing')
            ->with($this->owner, 24, 0)
            ->willReturn([$follow]);

        $response = $this->controller->following(
            'owner',
            new Request(),
            $this->profileVisibilityService,
            $this->userFollowRepository,
            new UserMapper(),
            null,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['total']);
        self::assertSame('followed1', $data['items'][0]['nickname']);
        self::assertArrayNotHasKey('email', $data['items'][0]);
    }

    public function testFollowingThrowsNotFoundExceptionWhenProfileIsNotVisible(): void
    {
        $this->profileVisibilityService->method('resolveVisibleUser')
            ->willThrowException(new ProfileNotFoundException('Профиль не найден.'));
        $this->userFollowRepository->expects($this->never())->method('findFollowing');

        $this->expectException(NotFoundHttpException::class);

        $this->controller->following(
            'hidden',
            new Request(),
            $this->profileVisibilityService,
            $this->userFollowRepository,
            new UserMapper(),
            null,
        );
    }

    public function testFavoritesReturnsPageForVisibleUser(): void
    {
        $game = new Game('Half-Life', 'half-life');
        $favorite = new GameFavorite($game, $this->owner);

        $this->profileVisibilityService->method('resolveVisibleUser')->willReturn($this->owner);
        $this->gameFavoriteRepository->method('countForUser')->willReturn(1);
        $this->gameFavoriteRepository->expects($this->once())
            ->method('findForUser')
            ->with($this->owner, 24, 0)
            ->willReturn([$favorite]);

        $response = $this->controller->favorites(
            'owner',
            new Request(),
            $this->profileVisibilityService,
            $this->gameFavoriteRepository,
            new GameMapper(),
            null,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame('Half-Life', $data['items'][0]['name']);
    }

    public function testFavoritesThrowsNotFoundExceptionWhenProfileIsNotVisible(): void
    {
        $this->profileVisibilityService->method('resolveVisibleUser')
            ->willThrowException(new ProfileNotFoundException('Профиль не найден.'));
        $this->gameFavoriteRepository->expects($this->never())->method('findForUser');

        $this->expectException(NotFoundHttpException::class);

        $this->controller->favorites(
            'hidden',
            new Request(),
            $this->profileVisibilityService,
            $this->gameFavoriteRepository,
            new GameMapper(),
            null,
        );
    }

    public function testGamesByStatusReturnsPageForValidStatus(): void
    {
        $game = new Game('Half-Life', 'half-life');
        $gameStatus = new GameStatus($game, $this->owner, GamePlaythroughStatus::InProgress);

        $this->profileVisibilityService->method('resolveVisibleUser')->willReturn($this->owner);
        $this->gameStatusRepository->method('countForUser')->willReturn(1);
        $this->gameStatusRepository->expects($this->once())
            ->method('findForUser')
            ->with($this->owner, GamePlaythroughStatus::InProgress, 24, 0)
            ->willReturn([$gameStatus]);

        $response = $this->controller->gamesByStatus(
            'owner',
            new Request(['status' => 'in_progress']),
            $this->profileVisibilityService,
            $this->gameStatusRepository,
            new GameMapper(),
            null,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame('Half-Life', $data['items'][0]['name']);
    }

    public function testGamesByStatusReturnsBadRequestForMissingStatus(): void
    {
        $this->profileVisibilityService->method('resolveVisibleUser')->willReturn($this->owner);
        $this->gameStatusRepository->expects($this->never())->method('findForUser');

        $response = $this->controller->gamesByStatus(
            'owner',
            new Request(),
            $this->profileVisibilityService,
            $this->gameStatusRepository,
            new GameMapper(),
            null,
        );

        self::assertSame(400, $response->getStatusCode());
    }

    public function testGamesByStatusThrowsNotFoundExceptionWhenProfileIsNotVisible(): void
    {
        $this->profileVisibilityService->method('resolveVisibleUser')
            ->willThrowException(new ProfileNotFoundException('Профиль не найден.'));

        $this->expectException(NotFoundHttpException::class);

        $this->controller->gamesByStatus(
            'hidden',
            new Request(['status' => 'in_progress']),
            $this->profileVisibilityService,
            $this->gameStatusRepository,
            new GameMapper(),
            null,
        );
    }

    private function setUserId(User $user, int $id): void
    {
        $reflection = new \ReflectionProperty($user, 'id');
        $reflection->setValue($user, $id);
    }
}
