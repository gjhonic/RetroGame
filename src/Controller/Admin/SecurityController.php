<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/** Вход/выход из админки. */
class SecurityController extends AbstractController
{
    /** Форма логина. Если пользователь уже вошёл — сразу в админку. */
    #[Route('/admin/login', name: 'admin_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils, #[CurrentUser] ?object $user): Response
    {
        if ($user !== null) {
            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('admin/security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    /** Пустой обработчик — выход перехватывается файрволом (см. security.yaml). */
    #[Route('/admin/logout', name: 'admin_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Перехватывается файрволом до вызова контроллера.');
    }
}
