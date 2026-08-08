<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Страница справочника издателей в админке — тонкая Twig-обёртка, данные
 * подгружает Vue-компонент через /api/admin/publishers (см. Api\Admin\PublisherApiController).
 */
#[IsGranted('ROLE_MODERATOR')]
class PublisherController extends AbstractController
{
    /** Список издателей. */
    #[Route('/admin/publishers', name: 'admin_publisher_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/publisher/index.html.twig');
    }
}
