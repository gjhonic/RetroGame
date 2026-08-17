<?php

namespace App\Controller\Cabinet;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Витрина своих игр — тонкие Twig-обёртки, данные подгружает Vue-компонент
 * через /api/our-games (см. OurGameApiController). Доступна без авторизации.
 */
class OurGameController extends AbstractController
{
    /** Список наших игр. */
    #[Route('/our-games', name: 'app_our_game_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('cabinet/our_game/index.html.twig');
    }

    /** Карточка одной игры со всеми подробностями. */
    #[Route('/our-games/{slug}', name: 'app_our_game_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        return $this->render('cabinet/our_game/show.html.twig', [
            'slug' => $slug,
        ]);
    }
}
