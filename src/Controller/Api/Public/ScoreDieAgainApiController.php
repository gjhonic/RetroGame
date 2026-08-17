<?php

namespace App\Controller\Api\Public;

use App\Dto\ScoreDieAgain\CreateScoreDieAgainRequest;
use App\Repository\ScoreDieAgainRepository;
use App\Service\ScoreDieAgain\CreateScoreDieAgainService;
use App\Service\ScoreDieAgain\ScoreDieAgainMapper;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** JSON API таблицы лидеров игры DIE//AGAIN — доступен всем без авторизации. */
#[Route('/api/score-die-again')]
#[OA\Tag(name: 'ScoreDieAgain')]
class ScoreDieAgainApiController extends AbstractController
{
    private const int PER_PAGE = 10;

    /** Таблица лидеров с постраничной навигацией. */
    #[Route('', name: 'app_api_score_die_again_list', methods: ['GET'])]
    #[OA\Parameter(
        name: 'page',
        description: 'Номер страницы (по умолчанию 1)',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1),
    )]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'Поле сортировки: kills, survivedSeconds, level, createdAt (по умолчанию kills)',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: 'kills'),
    )]
    #[OA\Parameter(
        name: 'sortDir',
        description: 'Направление сортировки: asc, desc (по умолчанию desc)',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: 'desc'),
    )]
    #[OA\Response(
        response: 200,
        description: 'Список результатов с постраничной навигацией',
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
        ScoreDieAgainRepository $scoreDieAgainRepository,
        ScoreDieAgainMapper $scoreDieAgainMapper,
    ): JsonResponse {
        $sortBy = $request->query->getString('sortBy', 'kills');
        $sortDir = strtolower($request->query->getString('sortDir', 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $total = $scoreDieAgainRepository->countAll();
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);

        $scores = $scoreDieAgainRepository->findForLeaderboard(
            $sortBy,
            $sortDir,
            self::PER_PAGE,
            ($page - 1) * self::PER_PAGE,
        );

        return $this->json([
            'items' => array_map($scoreDieAgainMapper->toListItem(...), $scores),
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /** Отправить результат раунда игры. */
    #[Route('', name: 'app_api_score_die_again_create', methods: ['POST'])]
    #[OA\RequestBody(content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'nickname', type: 'string'),
            new OA\Property(property: 'level', type: 'integer'),
            new OA\Property(property: 'survivedSeconds', type: 'integer'),
            new OA\Property(property: 'kills', type: 'integer'),
        ],
        type: 'object',
    ))]
    #[OA\Response(response: 201, description: 'Результат сохранён')]
    #[OA\Response(response: 400, description: 'Некорректное тело запроса')]
    #[OA\Response(response: 422, description: 'Ошибки валидации')]
    public function create(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CreateScoreDieAgainService $createScoreDieAgainService,
        ScoreDieAgainMapper $scoreDieAgainMapper,
    ): JsonResponse {
        try {
            $dto = $serializer->deserialize($request->getContent(), CreateScoreDieAgainRequest::class, 'json');
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

        $score = $createScoreDieAgainService->create($dto);

        return $this->json($scoreDieAgainMapper->toListItem($score), 201);
    }
}
