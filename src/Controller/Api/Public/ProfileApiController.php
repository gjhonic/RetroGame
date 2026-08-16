<?php

namespace App\Controller\Api\Public;

use App\Entity\Enum\GamePlaythroughStatus;
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
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * JSON API публичного профиля пользователя (`/profile/{nickname}`) — доступен без
 * авторизации, но только если владелец сделал профиль открытым (или это его собственный
 * профиль, см. ProfileVisibilityService). Закрытый/несуществующий профиль — 404.
 */
#[Route('/api/profile/{nickname}')]
#[OA\Tag(name: 'Profile')]
class ProfileApiController extends AbstractController
{
    private const int PER_PAGE = 24;

    /** Публичные данные профиля: ник, аватар, дата регистрации (без email). */
    #[Route('', name: 'app_api_profile_show', methods: ['GET'])]
    #[OA\Parameter(name: 'nickname', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Публичный профиль пользователя')]
    #[OA\Response(response: 404, description: 'Профиль не найден или закрыт')]
    public function show(
        string $nickname,
        ProfileVisibilityService $profileVisibilityService,
        UserFollowRepository $userFollowRepository,
        UserMapper $userMapper,
        #[CurrentUser] ?User $viewer,
    ): JsonResponse {
        try {
            $user = $profileVisibilityService->resolveVisibleUser($nickname, $viewer);
        } catch (ProfileNotFoundException $exception) {
            throw $this->createNotFoundException($exception->getMessage());
        }

        $isOwnProfile = $viewer !== null && $viewer->getId() !== null && $viewer->getId() === $user->getId();
        $isFollowing = $viewer !== null && !$isOwnProfile
            ? $userFollowRepository->findOneByFollowerAndFollowed($viewer, $user) !== null
            : null;

        return $this->json($userMapper->toPublicProfile(
            $user,
            $userFollowRepository->countFollowers($user),
            $userFollowRepository->countFollowing($user),
            $isOwnProfile,
            $isFollowing,
        ));
    }

    /** Подписчики владельца профиля (ник + аватар). */
    #[Route('/followers', name: 'app_api_profile_followers', methods: ['GET'])]
    #[OA\Parameter(name: 'nickname', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(
        name: 'page',
        description: 'Номер страницы (по умолчанию 1)',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1),
    )]
    #[OA\Response(
        response: 200,
        description: 'Список подписчиков с постраничной навигацией',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'items', type: 'array', items: new OA\Items(type: 'object')),
                new OA\Property(property: 'total', type: 'integer'),
                new OA\Property(property: 'page', type: 'integer'),
                new OA\Property(property: 'totalPages', type: 'integer'),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Профиль не найден или закрыт')]
    public function followers(
        string $nickname,
        Request $request,
        ProfileVisibilityService $profileVisibilityService,
        UserFollowRepository $userFollowRepository,
        UserMapper $userMapper,
        #[CurrentUser] ?User $viewer,
    ): JsonResponse {
        try {
            $user = $profileVisibilityService->resolveVisibleUser($nickname, $viewer);
        } catch (ProfileNotFoundException $exception) {
            throw $this->createNotFoundException($exception->getMessage());
        }

        $total = $userFollowRepository->countFollowers($user);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);

        $follows = $userFollowRepository->findFollowers($user, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        return $this->json([
            'items' => array_map(
                static fn (UserFollow $follow) => $userMapper->toProfileSummary($follow->getFollower()),
                $follows,
            ),
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /** Те, на кого подписан владелец профиля (ник + аватар). */
    #[Route('/following', name: 'app_api_profile_following', methods: ['GET'])]
    #[OA\Parameter(name: 'nickname', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(
        name: 'page',
        description: 'Номер страницы (по умолчанию 1)',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1),
    )]
    #[OA\Response(
        response: 200,
        description: 'Список подписок с постраничной навигацией',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'items', type: 'array', items: new OA\Items(type: 'object')),
                new OA\Property(property: 'total', type: 'integer'),
                new OA\Property(property: 'page', type: 'integer'),
                new OA\Property(property: 'totalPages', type: 'integer'),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Профиль не найден или закрыт')]
    public function following(
        string $nickname,
        Request $request,
        ProfileVisibilityService $profileVisibilityService,
        UserFollowRepository $userFollowRepository,
        UserMapper $userMapper,
        #[CurrentUser] ?User $viewer,
    ): JsonResponse {
        try {
            $user = $profileVisibilityService->resolveVisibleUser($nickname, $viewer);
        } catch (ProfileNotFoundException $exception) {
            throw $this->createNotFoundException($exception->getMessage());
        }

        $total = $userFollowRepository->countFollowing($user);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);

        $follows = $userFollowRepository->findFollowing($user, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        return $this->json([
            'items' => array_map(
                static fn (UserFollow $follow) => $userMapper->toProfileSummary($follow->getFollowed()),
                $follows,
            ),
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /** Любимые игры владельца профиля. */
    #[Route('/favorites', name: 'app_api_profile_favorites', methods: ['GET'])]
    #[OA\Parameter(name: 'nickname', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(
        name: 'page',
        description: 'Номер страницы (по умолчанию 1)',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1),
    )]
    #[OA\Response(
        response: 200,
        description: 'Список любимых игр с постраничной навигацией',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'items', type: 'array', items: new OA\Items(type: 'object')),
                new OA\Property(property: 'total', type: 'integer'),
                new OA\Property(property: 'page', type: 'integer'),
                new OA\Property(property: 'totalPages', type: 'integer'),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Профиль не найден или закрыт')]
    public function favorites(
        string $nickname,
        Request $request,
        ProfileVisibilityService $profileVisibilityService,
        GameFavoriteRepository $gameFavoriteRepository,
        GameMapper $gameMapper,
        #[CurrentUser] ?User $viewer,
    ): JsonResponse {
        try {
            $user = $profileVisibilityService->resolveVisibleUser($nickname, $viewer);
        } catch (ProfileNotFoundException $exception) {
            throw $this->createNotFoundException($exception->getMessage());
        }

        $total = $gameFavoriteRepository->countForUser($user);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);

        $favorites = $gameFavoriteRepository->findForUser($user, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        return $this->json([
            'items' => array_map(
                static fn (GameFavorite $favorite) => $gameMapper->toListItem($favorite->getGame()),
                $favorites,
            ),
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /** Игры владельца профиля с заданным статусом прохождения. */
    #[Route('/games', name: 'app_api_profile_games_by_status', methods: ['GET'])]
    #[OA\Parameter(name: 'nickname', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(
        name: 'status',
        description: 'Статус прохождения: planned, in_progress, completed, dropped',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Parameter(
        name: 'page',
        description: 'Номер страницы (по умолчанию 1)',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1),
    )]
    #[OA\Response(
        response: 200,
        description: 'Список игр с постраничной навигацией',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'items', type: 'array', items: new OA\Items(type: 'object')),
                new OA\Property(property: 'total', type: 'integer'),
                new OA\Property(property: 'page', type: 'integer'),
                new OA\Property(property: 'totalPages', type: 'integer'),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 400, description: 'Некорректный или отсутствующий статус')]
    #[OA\Response(response: 404, description: 'Профиль не найден или закрыт')]
    public function gamesByStatus(
        string $nickname,
        Request $request,
        ProfileVisibilityService $profileVisibilityService,
        GameStatusRepository $gameStatusRepository,
        GameMapper $gameMapper,
        #[CurrentUser] ?User $viewer,
    ): JsonResponse {
        try {
            $user = $profileVisibilityService->resolveVisibleUser($nickname, $viewer);
        } catch (ProfileNotFoundException $exception) {
            throw $this->createNotFoundException($exception->getMessage());
        }

        $status = GamePlaythroughStatus::tryFrom($request->query->getString('status', ''));
        if ($status === null) {
            return $this->json(['message' => 'Некорректный статус.'], 400);
        }

        $total = $gameStatusRepository->countForUser($user, $status);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);

        $statuses = $gameStatusRepository->findForUser($user, $status, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        return $this->json([
            'items' => array_map(
                static fn (GameStatus $gameStatus) => $gameMapper->toListItem($gameStatus->getGame()),
                $statuses,
            ),
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }
}
