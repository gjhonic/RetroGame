<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Страницы каталога игр в админке — тонкие Twig-обёртки, данные
 * подгружает Vue-компонент через /api/admin/games (см. Api\Admin\GameApiController).
 */
#[IsGranted('ROLE_MODERATOR')]
class GameController extends AbstractController
{
    /** Список игр. */
    #[Route('/admin/games', name: 'admin_game_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/game/index.html.twig');
    }

    /** Карточка одной игры со всеми подробностями. */
    #[Route('/admin/games/{id}', name: 'admin_game_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        return $this->render('admin/game/show.html.twig', [
            'id' => $id,
        ]);
    }
}
