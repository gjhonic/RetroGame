<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Страницы найденных на plati.market игр в админке — тонкие Twig-обёртки,
 * данные подгружает Vue-компонент через /api/admin/plati-games (см.
 * Api\Admin\PlatiGameApiController).
 */
#[IsGranted('ROLE_MODERATOR')]
class PlatiGameController extends AbstractController
{
    /** Список записей plati.market. */
    #[Route('/admin/plati-games', name: 'admin_plati_game_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/plati_game/index.html.twig');
    }

    /** Карточка одной записи plati.market со всеми подробностями. */
    #[Route('/admin/plati-games/{id}', name: 'admin_plati_game_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        return $this->render('admin/plati_game/show.html.twig', [
            'id' => $id,
        ]);
    }
}
