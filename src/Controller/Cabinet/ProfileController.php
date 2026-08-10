<?php

namespace App\Controller\Cabinet;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Профиль пользователя — тонкая Twig-обёртка, без данных из БД. */
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/cabinet/profile', name: 'cabinet_profile', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('cabinet/profile/index.html.twig');
    }
}
