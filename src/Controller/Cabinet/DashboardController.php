<?php

namespace App\Controller\Cabinet;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Главная страница личного кабинета — тонкая Twig-обёртка, без данных из БД. */
#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    #[Route('/cabinet', name: 'cabinet_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('cabinet/dashboard/index.html.twig');
    }
}
