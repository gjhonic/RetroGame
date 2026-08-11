<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Страницы записей импорта Steam-игр в админке — тонкие Twig-обёртки, данные
 * подгружает Vue-компонент через /api/admin/steam-games (см. Api\Admin\SteamGameApiController).
 */
#[IsGranted('ROLE_MODERATOR')]
class SteamGameController extends AbstractController
{
    /** Список Steam-записей. */
    #[Route('/admin/steam-games', name: 'admin_steam_game_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/steam_game/index.html.twig');
    }

    /** Карточка одной Steam-записи со всеми подробностями. */
    #[Route('/admin/steam-games/{id}', name: 'admin_steam_game_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        return $this->render('admin/steam_game/show.html.twig', [
            'id' => $id,
        ]);
    }
}
