<?php

namespace App\Controller\Api\Public;

use App\Repository\TakeCommentRepository;
use App\Repository\TakeReactionRepository;
use App\Repository\TakeRepository;
use App\Service\Take\TakeMapper;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** JSON API тэйков об играх — доступен всем без авторизации. */
#[Route('/api/takes')]
#[OA\Tag(name: 'Takes')]
class TakeApiController extends AbstractController
{
    private const int PER_PAGE = 20;

    /** Список тэйков с постраничной навигацией, опционально по конкретной игре. */
    #[Route('', name: 'app_api_take_list', methods: ['GET'])]
    #[OA\Parameter(
        name: 'page',
        description: 'Номер страницы (по умолчанию 1)',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1),
    )]
    #[OA\Parameter(
        name: 'filters[game]',
        description: 'Фильтр по ID игры',
        in: 'query',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Response(
        response: 200,
        description: 'Список тэйков с постраничной навигацией',
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
    ): JsonResponse {
        $rawFilters = $request->query->all('filters');
        $filters = [];
        $game = $rawFilters['game'] ?? null;
        if (\is_string($game) && trim($game) !== '') {
            $filters['game'] = trim($game);
        }

        $sortBy = $request->query->getString('sortBy', 'createdAt');
        $sortDir = strtolower($request->query->getString('sortDir', 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $total = $takeRepository->countForPublicList($filters);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);

        $takes = $takeRepository->findForPublicList(
            $filters,
            $sortBy,
            $sortDir,
            self::PER_PAGE,
            ($page - 1) * self::PER_PAGE,
        );

        $takeIds = array_map(static fn ($take): int => (int) $take->getId(), $takes);
        $reactionCounts = $takeReactionRepository->countByTypeForTakes($takeIds);

        return $this->json([
            'items' => array_map(
                static fn ($take) => $takeMapper->toListItem(
                    $take,
                    $reactionCounts[(int) $take->getId()]['like'],
                    $reactionCounts[(int) $take->getId()]['dislike'],
                    $takeCommentRepository->countForTake($take),
                ),
                $takes,
            ),
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /** Подробности тэйка вместе с первой страницей комментариев. */
    #[Route('/{id}', name: 'app_api_take_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Parameter(
        name: 'id',
        description: 'ID тэйка',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Response(response: 200, description: 'Подробности тэйка с комментариями')]
    #[OA\Response(response: 404, description: 'Тэйк не найден')]
    public function show(
        int $id,
        TakeRepository $takeRepository,
        TakeReactionRepository $takeReactionRepository,
        TakeCommentRepository $takeCommentRepository,
        TakeMapper $takeMapper,
    ): JsonResponse {
        $take = $takeRepository->find($id);
        if ($take === null) {
            throw $this->createNotFoundException('Тэйк не найден.');
        }

        $counts = $takeReactionRepository->countByTypeForTake($take);
        $comments = $takeCommentRepository->findForTake($take, self::PER_PAGE, 0);

        return $this->json($takeMapper->toDetail($take, $counts['like'], $counts['dislike'], $comments));
    }

    /** Постраничные комментарии тэйка. */
    #[Route('/{id}/comments', name: 'app_api_take_comments', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Parameter(
        name: 'id',
        description: 'ID тэйка',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Parameter(
        name: 'page',
        description: 'Номер страницы (по умолчанию 1)',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1),
    )]
    #[OA\Response(
        response: 200,
        description: 'Список комментариев с постраничной навигацией',
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
    #[OA\Response(response: 404, description: 'Тэйк не найден')]
    public function comments(
        int $id,
        Request $request,
        TakeRepository $takeRepository,
        TakeCommentRepository $takeCommentRepository,
        TakeMapper $takeMapper,
    ): JsonResponse {
        $take = $takeRepository->find($id);
        if ($take === null) {
            throw $this->createNotFoundException('Тэйк не найден.');
        }

        $total = $takeCommentRepository->countForTake($take);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);

        $comments = $takeCommentRepository->findForTake($take, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        return $this->json([
            'items' => array_map($takeMapper->toComment(...), $comments),
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }
}
