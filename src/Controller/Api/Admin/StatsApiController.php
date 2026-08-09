<?php

namespace App\Controller\Api\Admin;

use App\Repository\DeveloperRepository;
use App\Repository\GameRepository;
use App\Repository\GenreRepository;
use App\Repository\PublisherRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Сводная статистика для дашборда админки — используется Vue-компонентом Admin/Dashboard. */
#[Route('/api/admin/stats')]
#[IsGranted('ROLE_MODERATOR')]
#[OA\Tag(name: 'Admin/Stats')]
class StatsApiController extends AbstractController
{
    private const int TOP_GENRES_LIMIT = 6;

    #[Route('', name: 'app_api_admin_stats', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Количество записей по каталогу, игры по годам выхода, топ жанров, распределение оценок',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'totals', properties: [
                    new OA\Property(property: 'games', type: 'integer'),
                    new OA\Property(property: 'genres', type: 'integer'),
                    new OA\Property(property: 'developers', type: 'integer'),
                    new OA\Property(property: 'publishers', type: 'integer'),
                ], type: 'object'),
                new OA\Property(property: 'gamesByYear', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'year', type: 'integer'),
                        new OA\Property(property: 'count', type: 'integer'),
                    ],
                    type: 'object',
                )),
                new OA\Property(property: 'topGenres', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'count', type: 'integer'),
                    ],
                    type: 'object',
                )),
                new OA\Property(property: 'scoreDistribution', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'label', type: 'string'),
                        new OA\Property(property: 'count', type: 'integer'),
                    ],
                    type: 'object',
                )),
            ],
            type: 'object',
        ),
    )]
    public function index(
        GameRepository $gameRepository,
        GenreRepository $genreRepository,
        DeveloperRepository $developerRepository,
        PublisherRepository $publisherRepository,
    ): JsonResponse {
        $topGenres = $genreRepository->findForAdminList([], 'gamesCount', 'DESC', self::TOP_GENRES_LIMIT, 0);

        return $this->json([
            'totals' => [
                'games' => $gameRepository->countAll(),
                'genres' => $genreRepository->countForAdminList([]),
                'developers' => $developerRepository->countForAdminList([]),
                'publishers' => $publisherRepository->countForAdminList([]),
            ],
            'gamesByYear' => $gameRepository->findGamesCountByReleaseYear(),
            'topGenres' => array_map(
                static fn (array $genre): array => ['name' => $genre['name'], 'count' => $genre['gamesCount']],
                $topGenres,
            ),
            'scoreDistribution' => $gameRepository->findScoreDistribution(),
        ]);
    }
}
