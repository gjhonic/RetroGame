<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Страницы пользователей в админке — тонкие Twig-обёртки, данные подгружает
 * Vue-компонент через /api/admin/users (см. Api\Admin\UserApiController).
 */
#[IsGranted('ROLE_MODERATOR')]
class UserController extends AbstractController
{
    /** Список пользователей. */
    #[Route('/admin/users', name: 'admin_user_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/user/index.html.twig');
    }

    /** Карточка одного пользователя со всеми подробностями. */
    #[Route('/admin/users/{id}', name: 'admin_user_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        return $this->render('admin/user/show.html.twig', [
            'id' => $id,
        ]);
    }
}
