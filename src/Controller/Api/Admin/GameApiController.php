<?php

namespace App\Controller\Api\Admin;

use App\Entity\PlatiGame;
use App\Repository\GamePriceRepository;
use App\Repository\GameRepository;
use App\Repository\PlatiGamePriceRepository;
use App\Repository\PlatiGameRepository;
use App\Repository\SteamGameRepository;
use App\Service\Game\GameMapper;
use App\Service\GamePrice\GamePriceMapper;
use App\Service\Plati\GameImportService as PlatiGameImportService;
use App\Service\Plati\PriceImportService as PlatiPriceImportService;
use App\Service\PlatiGame\PlatiGameMapper;
use App\Service\Steam\PriceImportService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** JSON API каталога игр для админки — используется Vue-компонентами Admin/GameList, Admin/GameDetail. */
#[Route('/api/admin/games')]
#[IsGranted('ROLE_MODERATOR')]
#[OA\Tag(name: 'Admin/Games')]
class GameApiController extends AbstractController
{
    private const int DEFAULT_PER_PAGE = 25;
    private const int MAX_PER_PAGE = 100;

    /** Колонки, по которым разрешена сортировка (см. GameRepository::applyAdminSort()). */
    private const array SORTABLE_FIELDS = ['name', 'metacriticScore', 'releaseYear', 'developers', 'publishers'];

    /** Колонки, по которым разрешена фильтрация (query-параметр filters[<ключ>]). */
    private const array FILTERABLE_FIELDS = [
        'name', 'developer', 'publisher', 'genre', 'metacriticScore', 'releaseYear',
    ];

    /**
     * Страница списка игр для таблицы в админке (TanStack Table): поиск,
     * сортировка и постраничная навигация выполняются в БД, чтобы не грузить
     * в браузер весь каталог сразу.
     */
    #[Route('', name: 'app_api_admin_game_list', methods: ['GET'])]
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
        name: 'filters[developer]',
        description: 'Фильтр по разработчику (подстрока)',
        in: 'query',
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Parameter(
        name: 'filters[publisher]',
        description: 'Фильтр по издателю (подстрока)',
        in: 'query',
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Parameter(
        name: 'filters[genre]',
        description: 'Фильтр по жанру (подстрока)',
        in: 'query',
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Parameter(
        name: 'filters[metacriticScore]',
        description: 'Фильтр по оценке Metacritic (точное совпадение)',
        in: 'query',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Parameter(
        name: 'filters[releaseYear]',
        description: 'Фильтр по году выхода (точное совпадение)',
        in: 'query',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'Поле сортировки: name, metacriticScore, releaseYear, developers, publishers',
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
        description: 'Страница списка игр с постраничной навигацией',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'items', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', nullable: true),
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'slug', type: 'string'),
                        new OA\Property(property: 'coverImageUrl', type: 'string', nullable: true),
                        new OA\Property(property: 'description', type: 'string', nullable: true),
                        new OA\Property(property: 'metacriticScore', type: 'integer', nullable: true),
                        new OA\Property(property: 'releaseYear', type: 'string', nullable: true),
                        new OA\Property(property: 'developers', type: 'array', items: new OA\Items(type: 'string')),
                        new OA\Property(property: 'publishers', type: 'array', items: new OA\Items(type: 'string')),
                        new OA\Property(property: 'genres', type: 'array', items: new OA\Items(type: 'string')),
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
    public function list(Request $request, GameRepository $gameRepository, GameMapper $gameMapper): JsonResponse
    {
        $perPage = max(1, min(self::MAX_PER_PAGE, $request->query->getInt('perPage', self::DEFAULT_PER_PAGE)));

        $rawFilters = $request->query->all('filters');
        $filters = [];
        foreach (self::FILTERABLE_FIELDS as $field) {
            $value = $rawFilters[$field] ?? null;
            if (\is_string($value) && trim($value) !== '') {
                $filters[$field] = trim($value);
            }
        }

        $sortBy = $request->query->getString('sortBy', 'name');
        $sortField = \in_array($sortBy, self::SORTABLE_FIELDS, true) ? $sortBy : 'name';
        $sortDir = strtolower($request->query->getString('sortDir', 'asc')) === 'desc' ? 'DESC' : 'ASC';

        $total = $gameRepository->countForAdminList($filters);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);

        $games = $gameRepository->findForAdminList($filters, $sortField, $sortDir, $perPage, ($page - 1) * $perPage);

        return $this->json([
            'items' => array_map($gameMapper->toAdminListItem(...), $games),
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /** Подробности одной игры. */
    #[Route('/{id}', name: 'app_api_admin_game_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Parameter(
        name: 'id',
        description: 'ID игры',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Response(
        response: 200,
        description: 'Подробности игры',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'integer', nullable: true),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'slug', type: 'string'),
                new OA\Property(property: 'coverImageUrl', type: 'string', nullable: true),
                new OA\Property(property: 'screenshotUrls', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'rating', type: 'number', nullable: true),
                new OA\Property(property: 'metacriticScore', type: 'integer', nullable: true),
                new OA\Property(property: 'releaseDate', type: 'string', nullable: true),
                new OA\Property(property: 'developers', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'publishers', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'genres', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'platforms', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'steamGame', nullable: true, properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'steamAppId', type: 'integer'),
                ], type: 'object'),
                new OA\Property(property: 'platiGames', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'sellerName', type: 'string'),
                        new OA\Property(property: 'platiName', type: 'string'),
                        new OA\Property(property: 'url', type: 'string'),
                    ],
                    type: 'object',
                )),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Игра не найдена')]
    public function show(
        int $id,
        GameRepository $gameRepository,
        GameMapper $gameMapper,
        SteamGameRepository $steamGameRepository,
        PlatiGameRepository $platiGameRepository,
    ): JsonResponse {
        $game = $gameRepository->find($id);

        if ($game === null) {
            throw $this->createNotFoundException('Игра не найдена.');
        }

        $steamGame = $steamGameRepository->findOneByGame($game);

        return $this->json([
            ...$gameMapper->toDetail($game),
            'steamGame' => $steamGame !== null ? [
                'id' => $steamGame->getId(),
                'steamAppId' => $steamGame->getSteamAppId(),
            ] : null,
            'platiGames' => array_map(self::platiGameToLink(...), $platiGameRepository->findByGame($game)),
        ]);
    }

    /** @return array<string, mixed> */
    private static function platiGameToLink(PlatiGame $platiGame): array
    {
        return [
            'id' => $platiGame->getId(),
            'sellerName' => $platiGame->getSellerName(),
            'platiName' => $platiGame->getPlatiName(),
            'url' => $platiGame->getUrl(),
        ];
    }

    /**
     * Импортирует/обновляет цену игры на сегодня из Steam (регион RU) —
     * кнопка "Импортировать цену" на карточке игры в админке. Использует
     * тот же PriceImportService, что и крон app:games:import-prices.
     */
    #[Route(
        '/{id}/import-price',
        name: 'app_api_admin_game_import_price',
        methods: ['POST'],
        requirements: ['id' => '\d+'],
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'ID игры',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Response(
        response: 200,
        description: 'Снимок цены на сегодня',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'integer', nullable: true),
                new OA\Property(property: 'gameId', type: 'integer', nullable: true),
                new OA\Property(property: 'date', type: 'string'),
                new OA\Property(property: 'priceKopecks', type: 'integer', nullable: true),
                new OA\Property(property: 'currency', type: 'string'),
                new OA\Property(property: 'isFree', type: 'boolean'),
                new OA\Property(property: 'isAvailableInRussia', type: 'boolean'),
                new OA\Property(property: 'createdAt', type: 'string'),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Игра не найдена или не привязана к Steam')]
    #[OA\Response(response: 502, description: 'Не удалось получить данные от Steam')]
    public function importPrice(
        int $id,
        GameRepository $gameRepository,
        SteamGameRepository $steamGameRepository,
        PriceImportService $priceImportService,
        GamePriceMapper $gamePriceMapper,
    ): JsonResponse {
        $game = $gameRepository->find($id);
        if ($game === null) {
            throw $this->createNotFoundException('Игра не найдена.');
        }

        $steamGame = $steamGameRepository->findOneByGame($game);
        if ($steamGame === null) {
            throw $this->createNotFoundException('Игра не привязана к Steam — импорт цены недоступен.');
        }

        $price = $priceImportService->importPriceForGame($steamGame);
        if ($price === null) {
            return $this->json(
                ['errors' => ['steam' => ['Не удалось получить данные от Steam, попробуйте позже.']]],
                502,
            );
        }

        return $this->json($gamePriceMapper->toApi($price, $steamGame->getSteamAppId()));
    }

    /**
     * Цены игры для карточки в админке: текущее состояние и история по
     * дням (для графика) отдельно по Steam и по каждому найденному
     * продавцу на plati.market — тот же формат, что и у публичного
     * эндпоинта (см. Api\Public\GameApiController::priceHistory()).
     */
    #[Route(
        '/{id}/price-history',
        name: 'app_api_admin_game_price_history',
        methods: ['GET'],
        requirements: ['id' => '\d+'],
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'ID игры',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Response(
        response: 200,
        description: 'Цены игры в Steam и на plati.market: текущее состояние и история по дням',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'steam', properties: [
                    new OA\Property(property: 'isFree', type: 'boolean'),
                    new OA\Property(property: 'isAvailableInRussia', type: 'boolean'),
                    new OA\Property(property: 'priceKopecks', type: 'integer', nullable: true),
                    new OA\Property(property: 'store', type: 'string', nullable: true),
                    new OA\Property(property: 'storeUrl', type: 'string', nullable: true),
                    new OA\Property(property: 'history', type: 'array', items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'date', type: 'string'),
                            new OA\Property(property: 'priceKopecks', type: 'integer', nullable: true),
                        ],
                        type: 'object',
                    )),
                ], type: 'object'),
                new OA\Property(property: 'plati', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'sellerName', type: 'string'),
                        new OA\Property(property: 'url', type: 'string'),
                        new OA\Property(property: 'priceKopecks', type: 'integer', nullable: true),
                        new OA\Property(property: 'history', type: 'array', items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'date', type: 'string'),
                                new OA\Property(property: 'priceKopecks', type: 'integer', nullable: true),
                            ],
                            type: 'object',
                        )),
                    ],
                    type: 'object',
                )),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Игра не найдена')]
    public function priceHistory(
        int $id,
        GameRepository $gameRepository,
        SteamGameRepository $steamGameRepository,
        GamePriceRepository $gamePriceRepository,
        GamePriceMapper $gamePriceMapper,
        PlatiGameRepository $platiGameRepository,
        PlatiGamePriceRepository $platiGamePriceRepository,
        PlatiGameMapper $platiGameMapper,
    ): JsonResponse {
        $game = $gameRepository->find($id);
        if ($game === null) {
            throw $this->createNotFoundException('Игра не найдена.');
        }

        $steamAppId = $steamGameRepository->findOneByGame($game)?->getSteamAppId();
        $steamHistory = $gamePriceRepository->findHistoryForGame($game);

        $plati = array_map(
            static fn (PlatiGame $platiGame): array => $platiGameMapper->toPublicPriceSummary(
                $platiGame,
                $platiGamePriceRepository->findHistoryForPlatiGame($platiGame),
            ),
            $platiGameRepository->findByGame($game),
        );

        return $this->json([
            'steam' => $gamePriceMapper->toPublicSummary($steamHistory, $steamAppId),
            'plati' => $plati,
        ]);
    }

    /**
     * Ищет предложения по игре на plati.market вне очереди крона —
     * кнопка "Импортировать предложения" на карточке игры в админке.
     * В отличие от app:games:import-plati (до 3 продавцов на игру за
     * обход) сохраняет до 10 самых продаваемых совпавших предложений
     * сразу (см. Plati\GameImportService::importForGame()).
     */
    #[Route(
        '/{id}/import-plati',
        name: 'app_api_admin_game_import_plati',
        methods: ['POST'],
        requirements: ['id' => '\d+'],
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'ID игры',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Response(
        response: 200,
        description: 'Итог импорта: сообщение и актуальный список продавцов на plati.market',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'found', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'platiGames', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'sellerName', type: 'string'),
                        new OA\Property(property: 'platiName', type: 'string'),
                        new OA\Property(property: 'url', type: 'string'),
                    ],
                    type: 'object',
                )),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Игра не найдена')]
    public function importPlati(
        int $id,
        GameRepository $gameRepository,
        PlatiGameRepository $platiGameRepository,
        PlatiGameImportService $platiGameImportService,
    ): JsonResponse {
        $game = $gameRepository->find($id);
        if ($game === null) {
            throw $this->createNotFoundException('Игра не найдена.');
        }

        $result = $platiGameImportService->importForGame($game);

        return $this->json([
            'found' => $result->isFound(),
            'message' => $result->reason,
            'platiGames' => array_map(self::platiGameToLink(...), $platiGameRepository->findByGame($game)),
        ]);
    }

    /**
     * Импортирует/обновляет цены на сегодня у всех уже найденных продавцов
     * игры на plati.market — кнопка "Импортировать цены" в блоке Plati
     * игры в админке. Использует тот же PriceImportService, что и крон
     * app:games:import-plati-prices, но сразу по всем продавцам этой игры,
     * а не по курсору.
     */
    #[Route(
        '/{id}/import-plati-prices',
        name: 'app_api_admin_game_import_plati_prices',
        methods: ['POST'],
        requirements: ['id' => '\d+'],
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'ID игры',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Response(
        response: 200,
        description: 'Итог импорта цен',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'importedCount', type: 'integer'),
                new OA\Property(property: 'skippedCount', type: 'integer'),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Игра не найдена')]
    public function importPlatiPrices(
        int $id,
        GameRepository $gameRepository,
        PlatiGameRepository $platiGameRepository,
        PlatiPriceImportService $platiPriceImportService,
    ): JsonResponse {
        $game = $gameRepository->find($id);
        if ($game === null) {
            throw $this->createNotFoundException('Игра не найдена.');
        }

        $sellersCount = count($platiGameRepository->findByGame($game));
        $prices = $platiPriceImportService->importPricesForGame($game);

        return $this->json([
            'importedCount' => count($prices),
            'skippedCount' => $sellersCount - count($prices),
        ]);
    }
}
