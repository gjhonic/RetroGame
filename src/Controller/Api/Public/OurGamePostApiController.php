<?php

namespace App\Controller\Api\Public;

use App\Repository\OurGamePostRepository;
use App\Service\OurGamePost\OurGamePostMapper;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** JSON API опубликованных постов о своих играх — доступен всем без авторизации. */
#[Route('/api/our-game-posts')]
#[OA\Tag(name: 'OurGamePosts')]
class OurGamePostApiController extends AbstractController
{
    private const int DEFAULT_PER_PAGE = 20;
    private const int MAX_PER_PAGE = 50;

    /** Колонки, по которым разрешена фильтрация (query-параметр filters[<ключ>]). */
    private const array FILTERABLE_FIELDS = ['game', 'type'];

    /** Список опубликованных постов с постраничной навигацией, опционально по конкретной игре. */
    #[Route('', name: 'app_api_our_game_post_list', methods: ['GET'])]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer', default: 20))]
    #[OA\Parameter(
        name: 'filters[game]',
        description: 'Фильтр по ID игры',
        in: 'query',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Parameter(
        name: 'filters[type]',
        description: 'Фильтр по типу поста',
        in: 'query',
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Response(response: 200, description: 'Страница списка опубликованных постов с постраничной навигацией')]
    public function list(
        Request $request,
        OurGamePostRepository $ourGamePostRepository,
        OurGamePostMapper $ourGamePostMapper,
    ): JsonResponse {
        $perPage = max(1, min(self::MAX_PER_PAGE, $request->query->getInt('perPage', self::DEFAULT_PER_PAGE)));

        $rawFilters = $request->query->all('filters');
        $filters = [];
        foreach (self::FILTERABLE_FIELDS as $field) {
            $value = $rawFilters[$field] ?? null;
            if (\is_string($value) && trim($value) !== '') {
                $filters[$field] = trim($value);
            }
        }

        $total = $ourGamePostRepository->countPublishedForPublic($filters);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);

        $posts = $ourGamePostRepository->findPublishedForPublic(
            $filters,
            'postedAt',
            'DESC',
            $perPage,
            ($page - 1) * $perPage,
        );

        return $this->json([
            'items' => array_map($ourGamePostMapper->toAdminListItem(...), $posts),
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /** Подробности одного опубликованного поста. */
    #[Route('/{id}', name: 'app_api_our_game_post_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Подробности поста')]
    #[OA\Response(response: 404, description: 'Пост не найден')]
    public function show(
        int $id,
        OurGamePostRepository $ourGamePostRepository,
        OurGamePostMapper $ourGamePostMapper,
    ): JsonResponse {
        $post = $ourGamePostRepository->findOnePublishedById($id);

        if ($post === null) {
            throw $this->createNotFoundException('Пост не найден.');
        }

        return $this->json($ourGamePostMapper->toDetail($post));
    }
}
