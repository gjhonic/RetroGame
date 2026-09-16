<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Страницы найденных на ggsel.net игр в админке — тонкие Twig-обёртки, данные
 * подгружает Vue-компонент через /api/admin/ggsel-games (см. Api\Admin\GgselGameApiController).
 */
#[IsGranted('ROLE_MODERATOR')]
class GgselGameController extends AbstractController
{
    /** Список записей ggsel.net. */
    #[Route('/admin/ggsel-games', name: 'admin_ggsel_game_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/ggsel_game/index.html.twig');
    }

    /** Карточка одной записи ggsel.net со всеми подробностями. */
    #[Route('/admin/ggsel-games/{id}', name: 'admin_ggsel_game_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        return $this->render('admin/ggsel_game/show.html.twig', [
            'id' => $id,
        ]);
    }
}
