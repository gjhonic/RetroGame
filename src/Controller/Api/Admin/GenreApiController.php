<?php

namespace App\Controller\Api\Admin;

use App\Repository\GenreRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** JSON API справочника жанров для админки — используется Vue-компонентом Admin/NamedEntityList. */
#[Route('/api/admin/genres')]
#[IsGranted('ROLE_MODERATOR')]
#[OA\Tag(name: 'Admin/Genres')]
class GenreApiController extends AbstractController
{
    private const int DEFAULT_PER_PAGE = 25;
    private const int MAX_PER_PAGE = 100;

    /** Колонки, по которым разрешена сортировка (см. AdminNamedEntityListTrait::findForAdminList()). */
    private const array SORTABLE_FIELDS = ['name', 'gamesCount'];

    /**
     * Страница списка жанров для таблицы в админке: фильтр по названию,
     * сортировка и постраничная навигация выполняются в БД.
     */
    #[Route('', name: 'app_api_admin_genre_list', methods: ['GET'])]
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
        name: 'filters[name]',
        description: 'Фильтр по названию (подстрока)',
        in: 'query',
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'Поле сортировки: name, gamesCount',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: 'name'),
    )]
    #[OA\Parameter(
        name: 'sortDir',
        description: 'Направление сортировки: asc, desc',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: 'asc'),
    )]
    #[OA\Response(
        response: 200,
        description: 'Страница списка жанров с постраничной навигацией',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'items', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'gamesCount', type: 'integer'),
                    ],
                    type: 'object',
                )),
                new OA\Property(property: 'total', type: 'integer'),
                new OA\Property(property: 'page', type: 'integer'),
                new OA\Property(property: 'totalPages', type: 'integer'),
            ],
            type: 'object',
        ),
    )]
    public function list(Request $request, GenreRepository $genreRepository): JsonResponse
    {
        $perPage = max(1, min(self::MAX_PER_PAGE, $request->query->getInt('perPage', self::DEFAULT_PER_PAGE)));

        $rawFilters = $request->query->all('filters');
        $filters = [];
        $name = $rawFilters['name'] ?? null;
        if (\is_string($name) && trim($name) !== '') {
            $filters['name'] = trim($name);
        }

        $sortBy = $request->query->getString('sortBy', 'name');
        $sortField = \in_array($sortBy, self::SORTABLE_FIELDS, true) ? $sortBy : 'name';
        $sortDir = strtolower($request->query->getString('sortDir', 'asc')) === 'desc' ? 'DESC' : 'ASC';

        $total = $genreRepository->countForAdminList($filters);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);

        $items = $genreRepository->findForAdminList($filters, $sortField, $sortDir, $perPage, ($page - 1) * $perPage);

        return $this->json([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }
}
