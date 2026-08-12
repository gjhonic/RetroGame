<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Страницы своих игр в админке — тонкие Twig-обёртки, данные подгружает
 * Vue-компонент через /api/admin/our-games (см. Api\Admin\OurGameApiController).
 */
#[IsGranted('ROLE_MODERATOR')]
class OurGameController extends AbstractController
{
    /** Список своих игр. */
    #[Route('/admin/our-games', name: 'admin_our_game_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/our_game/index.html.twig');
    }

    /** Просмотр одной игры со всеми подробностями (без редактирования). */
    #[Route('/admin/our-games/{id}', name: 'admin_our_game_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        return $this->render('admin/our_game/show.html.twig', [
            'id' => $id,
        ]);
    }

    /** Редактирование одной игры: поля, картинки, ссылки на скачивание. */
    #[Route(
        '/admin/our-games/{id}/edit',
        name: 'admin_our_game_edit',
        methods: ['GET'],
        requirements: ['id' => '\d+'],
    )]
    public function edit(int $id): Response
    {
        return $this->render('admin/our_game/edit.html.twig', [
            'id' => $id,
        ]);
    }
}
