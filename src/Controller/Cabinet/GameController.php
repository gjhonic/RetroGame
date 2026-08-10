<?php

namespace App\Controller\Cabinet;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Каталог игр в личном кабинете — тонкие Twig-обёртки, данные подгружает
 * Vue-компонент через /api/games (см. GameApiController).
 */
#[IsGranted('ROLE_USER')]
class GameController extends AbstractController
{
    /** Список игр. */
    #[Route('/cabinet/games', name: 'cabinet_game_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('cabinet/game/index.html.twig');
    }

    /** Карточка одной игры со всеми подробностями и тэйками. */
    #[Route('/cabinet/games/{slug}', name: 'cabinet_game_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        return $this->render('cabinet/game/show.html.twig', [
            'slug' => $slug,
        ]);
    }
}
