<?php

namespace App\Controller\Api\Cabinet;

use App\Dto\Take\CreateTakeCommentRequest;
use App\Dto\Take\CreateTakeRequest;
use App\Dto\Take\SetTakeReactionRequest;
use App\Entity\Enum\TakeReactionType;
use App\Entity\User;
use App\Repository\TakeCommentRepository;
use App\Repository\TakeReactionRepository;
use App\Repository\TakeRepository;
use App\Service\Take\CreateTakeCommentService;
use App\Service\Take\CreateTakeService;
use App\Service\Take\Exceptions\GameNotFoundException;
use App\Service\Take\TakeMapper;
use App\Service\Take\TakeReactionService;
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

/** JSON API создания тэйков, комментариев и реакций текущим пользователем. */
#[Route('/api/cabinet/takes')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Cabinet/Takes')]
class TakeApiController extends AbstractController
{
    private const int PER_PAGE = 20;

    /** Лента своих тэйков (все игры), опционально не раньше даты $since. */
    #[Route('', name: 'app_api_cabinet_take_list', methods: ['GET'])]
    #[OA\Parameter(
        name: 'page',
        description: 'Номер страницы (по умолчанию 1)',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1),
    )]
    #[OA\Parameter(
        name: 'since',
        description: 'Не показывать тэйки старше этой даты (ISO 8601)',
        in: 'query',
        schema: new OA\Schema(type: 'string', format: 'date-time'),
    )]
    #[OA\Response(
        response: 200,
        description: 'Список своих тэйков с постраничной навигацией',
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
    public function list(
        Request $request,
        TakeRepository $takeRepository,
        TakeReactionRepository $takeReactionRepository,
        TakeCommentRepository $takeCommentRepository,
        TakeMapper $takeMapper,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $since = null;
        $sinceParam = $request->query->getString('since', '');
        if ($sinceParam !== '') {
            try {
                $since = new \DateTimeImmutable($sinceParam);
            } catch (\Exception) {
                $since = null;
            }
        }

        $total = $takeRepository->countForAuthor($user, $since);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);

        $takes = $takeRepository->findForAuthor($user, $since, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        $takeIds = array_map(static fn ($take): int => (int) $take->getId(), $takes);
        $reactionCounts = $takeReactionRepository->countByTypeForTakes($takeIds);
        $myReactions = $takeReactionRepository->findTypesForTakesAndUser($takeIds, $user);

        return $this->json([
            'items' => array_map(
                static fn ($take) => $takeMapper->toListItem(
                    $take,
                    $reactionCounts[(int) $take->getId()]['like'],
                    $reactionCounts[(int) $take->getId()]['dislike'],
                    $takeCommentRepository->countForTake($take),
                    $myReactions[(int) $take->getId()] ?? null,
                ),
                $takes,
            ),
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /** Создать тэйк об игре. */
    #[Route('', name: 'app_api_cabinet_take_create', methods: ['POST'])]
    #[OA\RequestBody(content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'gameId', type: 'integer'),
            new OA\Property(property: 'text', type: 'string'),
        ],
        type: 'object',
    ))]
    #[OA\Response(response: 201, description: 'Тэйк создан')]
    #[OA\Response(response: 400, description: 'Некорректное тело запроса')]
    #[OA\Response(response: 422, description: 'Ошибки валидации')]
    public function create(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CreateTakeService $createTakeService,
        TakeMapper $takeMapper,
        #[CurrentUser] User $user,
    ): JsonResponse {
        try {
            $dto = $serializer->deserialize($request->getContent(), CreateTakeRequest::class, 'json');
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

        try {
            $take = $createTakeService->create($user, $dto);
        } catch (GameNotFoundException $exception) {
            return $this->json(['errors' => ['gameId' => [$exception->getMessage()]]], 422);
        }

        return $this->json($takeMapper->toListItem($take, 0, 0, 0), 201);
    }

    /** Оставить комментарий к тэйку. */
    #[Route(
        '/{id}/comments',
        name: 'app_api_cabinet_take_create_comment',
        methods: ['POST'],
        requirements: ['id' => '\d+'],
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'ID тэйка',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\RequestBody(content: new OA\JsonContent(
        properties: [new OA\Property(property: 'text', type: 'string')],
        type: 'object',
    ))]
    #[OA\Response(response: 201, description: 'Комментарий создан')]
    #[OA\Response(response: 400, description: 'Некорректное тело запроса')]
    #[OA\Response(response: 404, description: 'Тэйк не найден')]
    #[OA\Response(response: 422, description: 'Ошибки валидации')]
    public function createComment(
        int $id,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CreateTakeCommentService $createTakeCommentService,
        TakeRepository $takeRepository,
        TakeMapper $takeMapper,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $take = $takeRepository->find($id);
        if ($take === null) {
            throw $this->createNotFoundException('Тэйк не найден.');
        }

        try {
            $dto = $serializer->deserialize($request->getContent(), CreateTakeCommentRequest::class, 'json');
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

        $comment = $createTakeCommentService->create($take, $user, $dto);

        return $this->json($takeMapper->toComment($comment), 201);
    }

    /** Поставить или сменить реакцию (лайк/дизлайк) на тэйк. */
    #[Route(
        '/{id}/reaction',
        name: 'app_api_cabinet_take_set_reaction',
        methods: ['PUT'],
        requirements: ['id' => '\d+'],
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'ID тэйка',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\RequestBody(content: new OA\JsonContent(
        properties: [new OA\Property(property: 'type', type: 'string', enum: ['like', 'dislike'])],
        type: 'object',
    ))]
    #[OA\Response(response: 200, description: 'Реакция сохранена')]
    #[OA\Response(response: 400, description: 'Некорректное тело запроса')]
    #[OA\Response(response: 404, description: 'Тэйк не найден')]
    #[OA\Response(response: 422, description: 'Ошибки валидации')]
    public function setReaction(
        int $id,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        TakeReactionService $takeReactionService,
        TakeReactionRepository $takeReactionRepository,
        TakeRepository $takeRepository,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $take = $takeRepository->find($id);
        if ($take === null) {
            throw $this->createNotFoundException('Тэйк не найден.');
        }

        try {
            $dto = $serializer->deserialize($request->getContent(), SetTakeReactionRequest::class, 'json');
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

        $reaction = $takeReactionService->setReaction($take, $user, TakeReactionType::from($dto->type));
        $counts = $takeReactionRepository->countByTypeForTake($take);

        return $this->json([
            'type' => $reaction->getType()->value,
            'likeCount' => $counts['like'],
            'dislikeCount' => $counts['dislike'],
        ]);
    }

    /** Снять реакцию с тэйка (идемпотентно). */
    #[Route(
        '/{id}/reaction',
        name: 'app_api_cabinet_take_remove_reaction',
        methods: ['DELETE'],
        requirements: ['id' => '\d+'],
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'ID тэйка',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Response(response: 200, description: 'Реакция снята')]
    #[OA\Response(response: 404, description: 'Тэйк не найден')]
    public function removeReaction(
        int $id,
        TakeReactionService $takeReactionService,
        TakeReactionRepository $takeReactionRepository,
        TakeRepository $takeRepository,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $take = $takeRepository->find($id);
        if ($take === null) {
            throw $this->createNotFoundException('Тэйк не найден.');
        }

        $takeReactionService->removeReaction($take, $user);
        $counts = $takeReactionRepository->countByTypeForTake($take);

        return $this->json([
            'type' => null,
            'likeCount' => $counts['like'],
            'dislikeCount' => $counts['dislike'],
        ]);
    }
}
