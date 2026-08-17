<?php

namespace App\Controller\Api\Public;

use App\Entity\User;
use App\Repository\GameFavoriteRepository;
use App\Repository\GameReactionRepository;
use App\Repository\GameRepository;
use App\Repository\GameStatusRepository;
use App\Repository\GenreRepository;
use App\Repository\PlatformRepository;
use App\Service\Game\GameMapper;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** JSON API каталога игр — используется Vue-компонентами каталога (GameCatalog, GameDetail). */
#[Route('/api/games')]
#[OA\Tag(name: 'Games')]
class GameApiController extends AbstractController
{
    private const int PER_PAGE = 24;

    /** Колонки, по которым разрешена сортировка (см. GameRepository::applyPublicSort()). */
    private const array SORTABLE_FIELDS = ['popularity', 'metacriticScore', 'releaseYear', 'name'];

    /** Колонки, по которым разрешена фильтрация (query-параметр filters[<ключ>]). */
    private const array FILTERABLE_FIELDS = ['name', 'genre', 'platform', 'releaseYearFrom', 'releaseYearTo'];

    /** Список игр с фильтрами, сортировкой и постраничной навигацией. */
    #[Route('', name: 'app_api_game_list', methods: ['GET'])]
    #[OA\Parameter(
        name: 'page',
        description: 'Номер страницы (по умолчанию 1)',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1),
    )]
    #[OA\Parameter(
        name: 'filters[name]',
        description: 'Поиск по названию (подстрока)',
        in: 'query',
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Parameter(
        name: 'filters[genre]',
        description: 'Фильтр по ID жанра',
        in: 'query',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Parameter(
        name: 'filters[platform]',
        description: 'Фильтр по ID платформы',
        in: 'query',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Parameter(
        name: 'filters[releaseYearFrom]',
        description: 'Фильтр по году выхода: не раньше',
        in: 'query',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Parameter(
        name: 'filters[releaseYearTo]',
        description: 'Фильтр по году выхода: не позже',
        in: 'query',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'Поле сортировки: popularity, metacriticScore, releaseYear, name',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: 'popularity'),
    )]
    #[OA\Parameter(
        name: 'sortDir',
        description: 'Направление сортировки: asc, desc',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: 'desc'),
    )]
    #[OA\Response(
        response: 200,
        description: 'Список игр с постраничной навигацией',
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
                        new OA\Property(property: 'popularity', type: 'integer', nullable: true),
                        new OA\Property(property: 'releaseYear', type: 'string', nullable: true),
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
        $rawFilters = $request->query->all('filters');
        $filters = [];
        foreach (self::FILTERABLE_FIELDS as $field) {
            $value = $rawFilters[$field] ?? null;
            if (\is_string($value) && trim($value) !== '') {
                $filters[$field] = trim($value);
            }
        }

        $sortBy = $request->query->getString('sortBy', 'popularity');
        $sortField = \in_array($sortBy, self::SORTABLE_FIELDS, true) ? $sortBy : 'popularity';
        $sortDir = strtolower($request->query->getString('sortDir', 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $total = $gameRepository->countForPublicCatalog($filters);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);

        $games = $gameRepository->findForPublicCatalog(
            $filters,
            $sortField,
            $sortDir,
            self::PER_PAGE,
            ($page - 1) * self::PER_PAGE,
        );

        return $this->json([
            'items' => array_map($gameMapper->toListItem(...), $games),
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /** Справочные данные для формы фильтров каталога: жанры, платформы, диапазон годов выхода. */
    #[Route('/filters', name: 'app_api_game_filters', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Справочные данные для фильтров каталога',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'genres', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'name', type: 'string'),
                    ],
                    type: 'object',
                )),
                new OA\Property(property: 'platforms', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'name', type: 'string'),
                    ],
                    type: 'object',
                )),
                new OA\Property(property: 'releaseYearMin', type: 'integer', nullable: true),
                new OA\Property(property: 'releaseYearMax', type: 'integer', nullable: true),
            ],
            type: 'object',
        ),
    )]
    public function filters(
        GameRepository $gameRepository,
        GenreRepository $genreRepository,
        PlatformRepository $platformRepository,
    ): JsonResponse {
        $yearRange = $gameRepository->findPublicReleaseYearRange();

        $genres = array_filter(
            $genreRepository->findBy([], ['name' => 'ASC']),
            static fn ($genre): bool => !\in_array($genre->getName(), GameMapper::HIDDEN_PUBLIC_GENRE_NAMES, true),
        );

        return $this->json([
            'genres' => array_values(array_map(
                static fn ($genre): array => ['id' => $genre->getId(), 'name' => $genre->getName()],
                $genres,
            )),
            'platforms' => array_map(
                static fn ($platform): array => ['id' => $platform->getId(), 'name' => $platform->getName()],
                $platformRepository->findBy([], ['name' => 'ASC']),
            ),
            'releaseYearMin' => $yearRange['min'] ?? null,
            'releaseYearMax' => $yearRange['max'] ?? null,
        ]);
    }

    /** Подробности одной игры. */
    #[Route('/{slug}', name: 'app_api_game_show', methods: ['GET'])]
    #[OA\Parameter(
        name: 'slug',
        description: 'Slug игры',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string'),
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
                new OA\Property(property: 'popularity', type: 'integer', nullable: true),
                new OA\Property(property: 'releaseDate', type: 'string', nullable: true),
                new OA\Property(property: 'developers', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'publishers', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'genres', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'platforms', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'likeCount', type: 'integer'),
                new OA\Property(property: 'dislikeCount', type: 'integer'),
                new OA\Property(property: 'myReaction', type: 'string', nullable: true),
                new OA\Property(property: 'myFavorite', type: 'boolean'),
                new OA\Property(property: 'myStatus', type: 'string', nullable: true),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Игра не найдена')]
    public function show(
        string $slug,
        GameRepository $gameRepository,
        GameReactionRepository $gameReactionRepository,
        GameFavoriteRepository $gameFavoriteRepository,
        GameStatusRepository $gameStatusRepository,
        GameMapper $gameMapper,
        #[CurrentUser] ?User $user,
    ): JsonResponse {
        $game = $gameRepository->findOneBy(['slug' => $slug]);

        if ($game === null || $gameMapper->isHiddenFromPublic($game)) {
            throw $this->createNotFoundException('Игра не найдена.');
        }

        $counts = $gameReactionRepository->countByTypeForGame($game);
        $myReaction = $user !== null ? $gameReactionRepository->findOneByGameAndUser($game, $user) : null;
        $myFavorite = $user !== null && $gameFavoriteRepository->findOneByGameAndUser($game, $user) !== null;
        $myStatus = $user !== null ? $gameStatusRepository->findOneByGameAndUser($game, $user) : null;

        return $this->json($gameMapper->toDetail(
            $game,
            $counts['like'],
            $counts['dislike'],
            $myReaction?->getType()->value,
            $myFavorite,
            $myStatus?->getStatus()->value,
        ));
    }
}
