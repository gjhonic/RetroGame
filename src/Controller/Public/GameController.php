<?php

namespace App\Controller\Public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Публичные страницы каталога игр — тонкие Twig-обёртки, данные
 * подгружает Vue-компонент через /api/games (см. GameApiController).
 */
class GameController extends AbstractController
{
    /** Список игр. */
    #[Route('/', name: 'app_game_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('public/game/index.html.twig');
    }

    /** Карточка одной игры со всеми подробностями. */
    #[Route('/games/{slug}', name: 'app_game_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        return $this->render('public/game/show.html.twig', [
            'slug' => $slug,
        ]);
    }
}
