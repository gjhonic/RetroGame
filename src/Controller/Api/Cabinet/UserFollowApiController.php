<?php

namespace App\Controller\Api\Cabinet;

use App\Entity\User;
use App\Repository\UserFollowRepository;
use App\Repository\UserRepository;
use App\Service\User\Exceptions\CannotFollowSelfException;
use App\Service\User\FollowService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** JSON API подписок на других пользователей — используется кнопкой "Подписаться" на публичном профиле. */
#[Route('/api/cabinet/users/{nickname}/follow')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Cabinet/Profile')]
class UserFollowApiController extends AbstractController
{
    /** Подписаться на пользователя по нику. */
    #[Route('', name: 'app_api_cabinet_user_follow', methods: ['PUT'])]
    #[OA\Parameter(name: 'nickname', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Подписка оформлена')]
    #[OA\Response(response: 400, description: 'Нельзя подписаться на самого себя')]
    #[OA\Response(response: 404, description: 'Пользователь не найден')]
    public function follow(
        string $nickname,
        UserRepository $userRepository,
        UserFollowRepository $userFollowRepository,
        FollowService $followService,
        #[CurrentUser] User $viewer,
    ): JsonResponse {
        $followed = $userRepository->findOneByNickname($nickname);
        if ($followed === null) {
            throw $this->createNotFoundException('Пользователь не найден.');
        }

        try {
            $followService->follow($viewer, $followed);
        } catch (CannotFollowSelfException $exception) {
            return $this->json(['message' => $exception->getMessage()], 400);
        }

        return $this->json([
            'isFollowing' => true,
            'followersCount' => $userFollowRepository->countFollowers($followed),
        ]);
    }

    /** Отписаться от пользователя по нику (идемпотентно). */
    #[Route('', name: 'app_api_cabinet_user_unfollow', methods: ['DELETE'])]
    #[OA\Parameter(name: 'nickname', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Подписка снята')]
    #[OA\Response(response: 404, description: 'Пользователь не найден')]
    public function unfollow(
        string $nickname,
        UserRepository $userRepository,
        UserFollowRepository $userFollowRepository,
        FollowService $followService,
        #[CurrentUser] User $viewer,
    ): JsonResponse {
        $followed = $userRepository->findOneByNickname($nickname);
        if ($followed === null) {
            throw $this->createNotFoundException('Пользователь не найден.');
        }

        $followService->unfollow($viewer, $followed);

        return $this->json([
            'isFollowing' => false,
            'followersCount' => $userFollowRepository->countFollowers($followed),
        ]);
    }
}
