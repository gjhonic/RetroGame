<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Страница справочника разработчиков в админке — тонкая Twig-обёртка, данные
 * подгружает Vue-компонент через /api/admin/developers (см. Api\Admin\DeveloperApiController).
 */
#[IsGranted('ROLE_MODERATOR')]
class DeveloperController extends AbstractController
{
    /** Список разработчиков. */
    #[Route('/admin/developers', name: 'admin_developer_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/developer/index.html.twig');
    }
}
