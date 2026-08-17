<?php

namespace App\Controller\Cabinet;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Каталог игр — тонкие Twig-обёртки, данные подгружает Vue-компонент через
 * /api/games (см. GameApiController). Доступен без авторизации; тэйки
 * (Cabinet/GameDetail.vue) видны всем, добавлять их может только вошедший
 * пользователь — управляется пропом isAuthenticated.
 */
class GameController extends AbstractController
{
    /** Список игр. */
    #[Route('/', name: 'app_game_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('cabinet/game/index.html.twig');
    }

    /** Карточка одной игры со всеми подробностями и тэйками. */
    #[Route('/games/{slug}', name: 'app_game_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        return $this->render('cabinet/game/show.html.twig', [
            'slug' => $slug,
        ]);
    }
}
