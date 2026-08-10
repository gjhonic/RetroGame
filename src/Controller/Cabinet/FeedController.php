<?php

namespace App\Controller\Cabinet;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Лента личного кабинета (главная страница) — тонкая Twig-обёртка, без данных из БД. */
#[IsGranted('ROLE_USER')]
class FeedController extends AbstractController
{
    #[Route('/cabinet', name: 'cabinet_feed', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('cabinet/feed/index.html.twig');
    }
}
