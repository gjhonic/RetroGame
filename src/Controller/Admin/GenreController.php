<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Страница справочника жанров в админке — тонкая Twig-обёртка, данные
 * подгружает Vue-компонент через /api/admin/genres (см. Api\Admin\GenreApiController).
 */
#[IsGranted('ROLE_MODERATOR')]
class GenreController extends AbstractController
{
    /** Список жанров. */
    #[Route('/admin/genres', name: 'admin_genre_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/genre/index.html.twig');
    }
}
