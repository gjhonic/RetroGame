<?php

namespace App\Controller\Api\Cabinet;

use App\Dto\Game\SetGameReactionRequest;
use App\Dto\Game\SetGameStatusRequest;
use App\Entity\Enum\GamePlaythroughStatus;
use App\Entity\Enum\GameReactionType;
use App\Entity\User;
use App\Repository\GameReactionRepository;
use App\Repository\GameRepository;
use App\Service\Game\GameFavoriteService;
use App\Service\Game\GameReactionService;
use App\Service\Game\GameStatusService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** JSON API реакций (лайк/дизлайк), избранного и статуса прохождения игры текущим пользователем. */
#[Route('/api/cabinet/games')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Cabinet/Games')]
class GameApiController extends AbstractController
{
    /** Поставить или сменить реакцию (лайк/дизлайк) на игру. */
    #[Route('/{slug}/reaction', name: 'app_api_cabinet_game_set_reaction', methods: ['PUT'])]
    #[OA\Parameter(
        name: 'slug',
        description: 'Slug игры',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\RequestBody(content: new OA\JsonContent(
        properties: [new OA\Property(property: 'type', type: 'string', enum: ['like', 'dislike'])],
        type: 'object',
    ))]
    #[OA\Response(response: 200, description: 'Реакция сохранена')]
    #[OA\Response(response: 400, description: 'Некорректное тело запроса')]
    #[OA\Response(response: 404, description: 'Игра не найдена')]
    #[OA\Response(response: 422, description: 'Ошибки валидации')]
    public function setReaction(
        string $slug,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        GameReactionService $gameReactionService,
        GameReactionRepository $gameReactionRepository,
        GameRepository $gameRepository,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $game = $gameRepository->findOneBy(['slug' => $slug]);
        if ($game === null) {
            throw $this->createNotFoundException('Игра не найдена.');
        }

        try {
            $dto = $serializer->deserialize($request->getContent(), SetGameReactionRequest::class, 'json');
        } catch (SerializerExceptionInterface) {
            return $this->json(['message' => 'Некорректное тело запроса.'], 400);
        }

        $violations = $validator->validate($dto);
        if (\count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()][] = $violation->getMessage();
            }

            return $this->json(['errors' => $errors], 422);
        }

        $reaction = $gameReactionService->setReaction($game, $user, GameReactionType::from($dto->type));
        $counts = $gameReactionRepository->countByTypeForGame($game);

        return $this->json([
            'type' => $reaction->getType()->value,
            'likeCount' => $counts['like'],
            'dislikeCount' => $counts['dislike'],
        ]);
    }

    /** Снять реакцию с игры (идемпотентно). */
    #[Route('/{slug}/reaction', name: 'app_api_cabinet_game_remove_reaction', methods: ['DELETE'])]
    #[OA\Parameter(
        name: 'slug',
        description: 'Slug игры',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Response(response: 200, description: 'Реакция снята')]
    #[OA\Response(response: 404, description: 'Игра не найдена')]
    public function removeReaction(
        string $slug,
        GameReactionService $gameReactionService,
        GameReactionRepository $gameReactionRepository,
        GameRepository $gameRepository,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $game = $gameRepository->findOneBy(['slug' => $slug]);
        if ($game === null) {
            throw $this->createNotFoundException('Игра не найдена.');
        }

        $gameReactionService->removeReaction($game, $user);
        $counts = $gameReactionRepository->countByTypeForGame($game);

        return $this->json([
            'type' => null,
            'likeCount' => $counts['like'],
            'dislikeCount' => $counts['dislike'],
        ]);
    }

    /** Добавить игру в избранное (идемпотентно). */
    #[Route('/{slug}/favorite', name: 'app_api_cabinet_game_add_favorite', methods: ['PUT'])]
    #[OA\Parameter(
        name: 'slug',
        description: 'Slug игры',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Response(response: 200, description: 'Игра добавлена в избранное')]
    #[OA\Response(response: 404, description: 'Игра не найдена')]
    public function addFavorite(
        string $slug,
        GameFavoriteService $gameFavoriteService,
        GameRepository $gameRepository,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $game = $gameRepository->findOneBy(['slug' => $slug]);
        if ($game === null) {
            throw $this->createNotFoundException('Игра не найдена.');
        }

        $gameFavoriteService->addFavorite($game, $user);

        return $this->json(['favorite' => true]);
    }

    /** Убрать игру из избранного (идемпотентно). */
    #[Route('/{slug}/favorite', name: 'app_api_cabinet_game_remove_favorite', methods: ['DELETE'])]
    #[OA\Parameter(
        name: 'slug',
        description: 'Slug игры',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Response(response: 200, description: 'Игра убрана из избранного')]
    #[OA\Response(response: 404, description: 'Игра не найдена')]
    public function removeFavorite(
        string $slug,
        GameFavoriteService $gameFavoriteService,
        GameRepository $gameRepository,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $game = $gameRepository->findOneBy(['slug' => $slug]);
        if ($game === null) {
            throw $this->createNotFoundException('Игра не найдена.');
        }

        $gameFavoriteService->removeFavorite($game, $user);

        return $this->json(['favorite' => false]);
    }

    /** Поставить или сменить статус прохождения игры. */
    #[Route('/{slug}/status', name: 'app_api_cabinet_game_set_status', methods: ['PUT'])]
    #[OA\Parameter(
        name: 'slug',
        description: 'Slug игры',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\RequestBody(content: new OA\JsonContent(
        properties: [
            new OA\Property(
                property: 'status',
                type: 'string',
                enum: ['planned', 'in_progress', 'completed', 'dropped'],
            ),
        ],
        type: 'object',
    ))]
    #[OA\Response(response: 200, description: 'Статус сохранён')]
    #[OA\Response(response: 400, description: 'Некорректное тело запроса')]
    #[OA\Response(response: 404, description: 'Игра не найдена')]
    #[OA\Response(response: 422, description: 'Ошибки валидации')]
    public function setStatus(
        string $slug,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        GameStatusService $gameStatusService,
        GameRepository $gameRepository,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $game = $gameRepository->findOneBy(['slug' => $slug]);
        if ($game === null) {
            throw $this->createNotFoundException('Игра не найдена.');
        }

        try {
            $dto = $serializer->deserialize($request->getContent(), SetGameStatusRequest::class, 'json');
        } catch (SerializerExceptionInterface) {
            return $this->json(['message' => 'Некорректное тело запроса.'], 400);
        }

        $violations = $validator->validate($dto);
        if (\count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()][] = $violation->getMessage();
            }

            return $this->json(['errors' => $errors], 422);
        }

        $status = $gameStatusService->setStatus($game, $user, GamePlaythroughStatus::from($dto->status));

        return $this->json(['status' => $status->getStatus()->value]);
    }

    /** Снять статус прохождения игры (идемпотентно). */
    #[Route('/{slug}/status', name: 'app_api_cabinet_game_remove_status', methods: ['DELETE'])]
    #[OA\Parameter(
        name: 'slug',
        description: 'Slug игры',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Response(response: 200, description: 'Статус снят')]
    #[OA\Response(response: 404, description: 'Игра не найдена')]
    public function removeStatus(
        string $slug,
        GameStatusService $gameStatusService,
        GameRepository $gameRepository,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $game = $gameRepository->findOneBy(['slug' => $slug]);
        if ($game === null) {
            throw $this->createNotFoundException('Игра не найдена.');
        }

        $gameStatusService->removeStatus($game, $user);

        return $this->json(['status' => null]);
    }
}
