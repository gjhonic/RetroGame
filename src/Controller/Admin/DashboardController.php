<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Главная страница админки. */
#[IsGranted('ROLE_MODERATOR')]
class DashboardController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        return $this->render('admin/dashboard/index.html.twig', [
            'user' => $user,
        ]);
    }
}
