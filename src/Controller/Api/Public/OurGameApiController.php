<?php

namespace App\Controller\Api\Public;

use App\Repository\OurGameRepository;
use App\Service\OurGame\OurGameMapper;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * JSON API витрины своих игр — доступен всем без авторизации, используется
 * Public/OurGameList, Public/OurGameDetail. Отдельной кабинетной страницы/API
 * нет — ссылка "Наши игры" в сайдбаре кабинета ведёт на публичную витрину
 * (данные не зависят от пользователя, см. GameApiController — тот же приём).
 */
#[Route('/api/our-games')]
#[OA\Tag(name: 'OurGames')]
class OurGameApiController extends AbstractController
{
    /** Список опубликованных своих игр (обложка + название) для витрины. */
    #[Route('', name: 'app_api_our_game_list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Список опубликованных своих игр',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'items', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', nullable: true),
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'slug', type: 'string'),
                        new OA\Property(property: 'coverImageUrl', type: 'string', nullable: true),
                        new OA\Property(property: 'currentVersion', type: 'string', nullable: true),
                        new OA\Property(property: 'releaseDate', type: 'string', nullable: true),
                        new OA\Property(property: 'genres', type: 'array', items: new OA\Items(type: 'string')),
                    ],
                    type: 'object',
                )),
            ],
            type: 'object',
        ),
    )]
    public function list(OurGameRepository $ourGameRepository, OurGameMapper $ourGameMapper): JsonResponse
    {
        return $this->json([
            'items' => array_map($ourGameMapper->toAdminListItem(...), $ourGameRepository->findPublishedForPublic()),
        ]);
    }

    /** Подробности опубликованной игры по slug. */
    #[Route('/{slug}', name: 'app_api_our_game_show', methods: ['GET'])]
    #[OA\Parameter(
        name: 'slug',
        description: 'Slug игры',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Response(response: 200, description: 'Подробности игры')]
    #[OA\Response(response: 404, description: 'Игра не найдена или не опубликована')]
    public function show(
        string $slug,
        OurGameRepository $ourGameRepository,
        OurGameMapper $ourGameMapper,
    ): JsonResponse {
        $game = $ourGameRepository->findOnePublishedBySlug($slug);

        if ($game === null) {
            throw $this->createNotFoundException('Игра не найдена.');
        }

        return $this->json($ourGameMapper->toDetail($game));
    }
}
