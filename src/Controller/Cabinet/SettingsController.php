<?php

namespace App\Controller\Cabinet;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Настройки личного кабинета — тонкая Twig-обёртка, без данных из БД. */
#[IsGranted('ROLE_USER')]
class SettingsController extends AbstractController
{
    #[Route('/cabinet/settings', name: 'cabinet_settings', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('cabinet/settings/index.html.twig');
    }
}
