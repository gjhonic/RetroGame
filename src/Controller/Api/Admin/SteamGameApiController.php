<?php

namespace App\Controller\Api\Admin;

use App\Repository\SteamGameRepository;
use App\Service\SteamGame\SteamGameMapper;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** JSON API записей импорта Steam-игр для админки — используется Vue-компонентами Admin/SteamGameList, Admin/SteamGameDetail. */
#[Route('/api/admin/steam-games')]
#[IsGranted('ROLE_MODERATOR')]
#[OA\Tag(name: 'Admin/SteamGames')]
class SteamGameApiController extends AbstractController
{
    private const int DEFAULT_PER_PAGE = 25;
    private const int MAX_PER_PAGE = 100;

    /** Колонки, по которым разрешена сортировка (см. SteamGameRepository::applyAdminSort()). */
    private const array SORTABLE_FIELDS = ['steamAppId', 'status', 'attempts', 'fetchedAt', 'lastAttemptAt'];

    /** Колонки, по которым разрешена фильтрация (query-параметр filters[<ключ>]). */
    private const array FILTERABLE_FIELDS = ['steamAppId', 'status', 'game'];

    /**
     * Страница списка Steam-записей для таблицы в админке (TanStack Table):
     * поиск, сортировка и постраничная навигация выполняются в БД.
     */
    #[Route('', name: 'app_api_admin_steam_game_list', methods: ['GET'])]
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
        name: 'filters[steamAppId]',
        description: 'Фильтр по appid игры в Steam (точное совпадение)',
        in: 'query',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Parameter(
        name: 'filters[status]',
        description: 'Фильтр по статусу: pending, success, failed',
        in: 'query',
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Parameter(
        name: 'filters[game]',
        description: 'Фильтр по названию связанной игры (подстрока)',
        in: 'query',
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'Поле сортировки: steamAppId, status, attempts, fetchedAt, lastAttemptAt',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: 'steamAppId'),
    )]
    #[OA\Parameter(
        name: 'sortDir',
        description: 'Направление сортировки: asc, desc',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: 'asc'),
    )]
    #[OA\Response(
        response: 200,
        description: 'Страница списка Steam-записей с постраничной навигацией',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'items', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', nullable: true),
                        new OA\Property(property: 'steamAppId', type: 'integer'),
                        new OA\Property(property: 'status', type: 'string'),
                        new OA\Property(property: 'gameId', type: 'integer', nullable: true),
                        new OA\Property(property: 'gameName', type: 'string', nullable: true),
                        new OA\Property(property: 'gameCoverImageUrl', type: 'string', nullable: true),
                        new OA\Property(property: 'attempts', type: 'integer'),
                        new OA\Property(property: 'fetchedAt', type: 'string', nullable: true),
                        new OA\Property(property: 'lastAttemptAt', type: 'string', nullable: true),
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
        SteamGameRepository $steamGameRepository,
        SteamGameMapper $steamGameMapper,
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

        $sortBy = $request->query->getString('sortBy', 'steamAppId');
        $sortField = \in_array($sortBy, self::SORTABLE_FIELDS, true) ? $sortBy : 'steamAppId';
        $sortDir = strtolower($request->query->getString('sortDir', 'asc')) === 'desc' ? 'DESC' : 'ASC';

        $total = $steamGameRepository->countForAdminList($filters);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);

        $steamGames = $steamGameRepository->findForAdminList(
            $filters,
            $sortField,
            $sortDir,
            $perPage,
            ($page - 1) * $perPage,
        );

        return $this->json([
            'items' => array_map($steamGameMapper->toAdminListItem(...), $steamGames),
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /** Подробности одной Steam-записи, включая сырой JSON от Steam appdetails. */
    #[Route('/{id}', name: 'app_api_admin_steam_game_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Parameter(
        name: 'id',
        description: 'ID записи',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Response(
        response: 200,
        description: 'Подробности Steam-записи',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'integer', nullable: true),
                new OA\Property(property: 'steamAppId', type: 'integer'),
                new OA\Property(property: 'status', type: 'string'),
                new OA\Property(property: 'gameId', type: 'integer', nullable: true),
                new OA\Property(property: 'gameName', type: 'string', nullable: true),
                new OA\Property(property: 'gameSlug', type: 'string', nullable: true),
                new OA\Property(property: 'gameCoverImageUrl', type: 'string', nullable: true),
                new OA\Property(property: 'lastError', type: 'string', nullable: true),
                new OA\Property(property: 'attempts', type: 'integer'),
                new OA\Property(property: 'fetchedAt', type: 'string', nullable: true),
                new OA\Property(property: 'lastAttemptAt', type: 'string', nullable: true),
                new OA\Property(property: 'rawData', type: 'object', nullable: true),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Запись не найдена')]
    public function show(
        int $id,
        SteamGameRepository $steamGameRepository,
        SteamGameMapper $steamGameMapper,
    ): JsonResponse {
        $steamGame = $steamGameRepository->find($id);

        if ($steamGame === null) {
            throw $this->createNotFoundException('Запись не найдена.');
        }

        return $this->json($steamGameMapper->toDetail($steamGame));
    }
}
