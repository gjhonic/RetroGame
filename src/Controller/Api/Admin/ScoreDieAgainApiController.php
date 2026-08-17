<?php

namespace App\Controller\Api\Admin;

use App\Repository\ScoreDieAgainRepository;
use App\Service\ScoreDieAgain\ScoreDieAgainMapper;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** JSON API таблицы лидеров игры DIE//AGAIN для админки — список и сброс. */
#[Route('/api/admin/score-die-again')]
#[IsGranted('ROLE_MODERATOR')]
#[OA\Tag(name: 'Admin/ScoreDieAgain')]
class ScoreDieAgainApiController extends AbstractController
{
    private const int DEFAULT_PER_PAGE = 25;
    private const int MAX_PER_PAGE = 100;

    /** Страница таблицы лидеров для админки. */
    #[Route('', name: 'app_api_admin_score_die_again_list', methods: ['GET'])]
    #[OA\Parameter(
        name: 'page',
        description: 'Номер страницы (по умолчанию 1)',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1),
    )]
    #[OA\Parameter(
        name: 'perPage',
        description: 'Строк на странице (по умолчанию 25, максимум 100)',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 25),
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
        description: 'Страница списка результатов с постраничной навигацией',
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
        $perPage = max(1, min(self::MAX_PER_PAGE, $request->query->getInt('perPage', self::DEFAULT_PER_PAGE)));
        $sortBy = $request->query->getString('sortBy', 'kills');
        $sortDir = strtolower($request->query->getString('sortDir', 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $total = $scoreDieAgainRepository->countAll();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);

        $scores = $scoreDieAgainRepository->findForLeaderboard(
            $sortBy,
            $sortDir,
            $perPage,
            ($page - 1) * $perPage,
        );

        return $this->json([
            'items' => array_map($scoreDieAgainMapper->toListItem(...), $scores),
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /** Сбрасывает таблицу лидеров — безвозвратно удаляет все результаты. */
    #[Route('', name: 'app_api_admin_score_die_again_reset', methods: ['DELETE'])]
    #[OA\Response(
        response: 200,
        description: 'Таблица очищена',
        content: new OA\JsonContent(
            properties: [new OA\Property(property: 'deleted', type: 'integer')],
            type: 'object',
        ),
    )]
    public function reset(ScoreDieAgainRepository $scoreDieAgainRepository): JsonResponse
    {
        $deleted = $scoreDieAgainRepository->deleteAll();

        return $this->json(['deleted' => $deleted]);
    }
}
