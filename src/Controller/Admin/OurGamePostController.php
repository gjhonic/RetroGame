<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Страницы постов о своих играх в админке — тонкие Twig-обёртки, данные
 * подгружает Vue-компонент через /api/admin/our-game-posts (см. Api\Admin\OurGamePostApiController).
 */
#[IsGranted('ROLE_MODERATOR')]
class OurGamePostController extends AbstractController
{
    /** Список постов. */
    #[Route('/admin/our-game-posts', name: 'admin_our_game_post_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/our_game_post/index.html.twig');
    }

    /** Создание поста; опциональный ?gameId= предзаполняет игру (переход с карточки игры). */
    #[Route('/admin/our-game-posts/new', name: 'admin_our_game_post_new', methods: ['GET'])]
    public function new(Request $request): Response
    {
        return $this->render('admin/our_game_post/new.html.twig', [
            'gameId' => $request->query->get('gameId'),
        ]);
    }

    /** Просмотр одного поста (без редактирования). */
    #[Route(
        '/admin/our-game-posts/{id}',
        name: 'admin_our_game_post_show',
        methods: ['GET'],
        requirements: ['id' => '\d+'],
    )]
    public function show(int $id): Response
    {
        return $this->render('admin/our_game_post/show.html.twig', [
            'id' => $id,
        ]);
    }

    /** Редактирование поста: поля и картинка. */
    #[Route(
        '/admin/our-game-posts/{id}/edit',
        name: 'admin_our_game_post_edit',
        methods: ['GET'],
        requirements: ['id' => '\d+'],
    )]
    public function edit(int $id): Response
    {
        return $this->render('admin/our_game_post/edit.html.twig', [
            'id' => $id,
        ]);
    }
}
