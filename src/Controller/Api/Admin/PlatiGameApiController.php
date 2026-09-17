<?php

namespace App\Controller\Api\Admin;

use App\Repository\PlatiGameRepository;
use App\Service\PlatiGame\PlatiGameMapper;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** JSON API записей о наличии игр на plati.market для админки — используется Vue-компонентами Admin/PlatiGameList, Admin/PlatiGameDetail. */
#[Route('/api/admin/plati-games')]
#[IsGranted('ROLE_MODERATOR')]
#[OA\Tag(name: 'Admin/PlatiGames')]
class PlatiGameApiController extends AbstractController
{
    private const int DEFAULT_PER_PAGE = 25;
    private const int MAX_PER_PAGE = 100;

    /** Колонки, по которым разрешена сортировка (см. PlatiGameRepository::applyAdminSort()). */
    private const array SORTABLE_FIELDS = ['createdAt', 'updatedAt', 'game', 'sellerName'];

    /** Колонки, по которым разрешена фильтрация (query-параметр filters[<ключ>]). */
    private const array FILTERABLE_FIELDS = ['game', 'url', 'sellerName'];

    /**
     * Страница списка найденных на plati.market игр для таблицы в админке
     * (TanStack Table): фильтры, сортировка и постраничная навигация
     * выполняются в БД.
     */
    #[Route('', name: 'app_api_admin_plati_game_list', methods: ['GET'])]
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
        name: 'filters[game]',
        description: 'Фильтр по названию связанной игры (подстрока)',
        in: 'query',
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Parameter(
        name: 'filters[url]',
        description: 'Фильтр по ссылке на товар на plati.market (подстрока)',
        in: 'query',
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Parameter(
        name: 'filters[sellerName]',
        description: 'Фильтр по имени продавца (подстрока)',
        in: 'query',
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'Поле сортировки: createdAt, updatedAt, game, sellerName',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: 'createdAt'),
    )]
    #[OA\Parameter(
        name: 'sortDir',
        description: 'Направление сортировки: asc, desc',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: 'desc'),
    )]
    #[OA\Response(
        response: 200,
        description: 'Страница списка найденных на plati.market игр с постраничной навигацией',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'items', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', nullable: true),
                        new OA\Property(property: 'gameId', type: 'integer', nullable: true),
                        new OA\Property(property: 'gameName', type: 'string', nullable: true),
                        new OA\Property(property: 'gameCoverImageUrl', type: 'string', nullable: true),
                        new OA\Property(property: 'url', type: 'string'),
                        new OA\Property(property: 'sellerName', type: 'string'),
                        new OA\Property(property: 'createdAt', type: 'string'),
                        new OA\Property(property: 'updatedAt', type: 'string'),
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
    public function list(
        Request $request,
        PlatiGameRepository $platiGameRepository,
        PlatiGameMapper $platiGameMapper,
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

        $sortBy = $request->query->getString('sortBy', 'createdAt');
        $sortField = \in_array($sortBy, self::SORTABLE_FIELDS, true) ? $sortBy : 'createdAt';
        $sortDir = strtolower($request->query->getString('sortDir', 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $total = $platiGameRepository->countForAdminList($filters);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);

        $platiGames = $platiGameRepository->findForAdminList(
            $filters,
            $sortField,
            $sortDir,
            $perPage,
            ($page - 1) * $perPage,
        );

        return $this->json([
            'items' => array_map($platiGameMapper->toAdminListItem(...), $platiGames),
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /** Подробности одной найденной на plati.market игры. */
    #[Route('/{id}', name: 'app_api_admin_plati_game_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Parameter(
        name: 'id',
        description: 'ID записи',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Response(
        response: 200,
        description: 'Подробности записи plati.market',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'integer', nullable: true),
                new OA\Property(property: 'gameId', type: 'integer', nullable: true),
                new OA\Property(property: 'gameName', type: 'string', nullable: true),
                new OA\Property(property: 'gameSlug', type: 'string', nullable: true),
                new OA\Property(property: 'gameCoverImageUrl', type: 'string', nullable: true),
                new OA\Property(property: 'url', type: 'string'),
                new OA\Property(property: 'sellerName', type: 'string'),
                new OA\Property(property: 'createdAt', type: 'string'),
                new OA\Property(property: 'updatedAt', type: 'string'),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Запись не найдена')]
    public function show(
        int $id,
        PlatiGameRepository $platiGameRepository,
        PlatiGameMapper $platiGameMapper,
    ): JsonResponse {
        $platiGame = $platiGameRepository->find($id);

        if ($platiGame === null) {
            throw $this->createNotFoundException('Запись не найдена.');
        }

        return $this->json($platiGameMapper->toDetail($platiGame));
    }
}
