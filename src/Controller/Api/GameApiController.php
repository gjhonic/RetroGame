<?php

namespace App\Controller\Api;

use App\Repository\GameRepository;
use App\Service\Game\GameMapper;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** JSON API каталога игр — используется Vue-компонентами каталога (GameCatalog, GameDetail). */
#[Route('/api/games')]
#[OA\Tag(name: 'Games')]
class GameApiController extends AbstractController
{
    private const int PER_PAGE = 24;

    /** Список игр с постраничной навигацией. */
    #[Route('', name: 'app_api_game_list', methods: ['GET'])]
    #[OA\Parameter(
        name: 'page',
        description: 'Номер страницы (по умолчанию 1)',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1),
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
        $page = max(1, $request->query->getInt('page', 1));
        $total = $gameRepository->count([]);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $totalPages);

        $games = $gameRepository->findBy(
            criteria: [],
            orderBy: ['name' => 'ASC'],
            limit: self::PER_PAGE,
            offset: ($page - 1) * self::PER_PAGE,
        );

        return $this->json([
            'items' => array_map($gameMapper->toListItem(...), $games),
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
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
                new OA\Property(property: 'releaseDate', type: 'string', nullable: true),
                new OA\Property(property: 'developers', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'publishers', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'genres', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'platforms', type: 'array', items: new OA\Items(type: 'string')),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Игра не найдена')]
    public function show(string $slug, GameRepository $gameRepository, GameMapper $gameMapper): JsonResponse
    {
        $game = $gameRepository->findOneBy(['slug' => $slug]);

        if ($game === null) {
            throw $this->createNotFoundException('Игра не найдена.');
        }

        return $this->json($gameMapper->toDetail($game));
    }
}
