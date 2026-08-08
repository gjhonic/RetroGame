<?php

namespace App\Controller\Public;

use App\Repository\GameRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/** Вход/выход из админки. */
class SecurityController extends AbstractController
{
    private const int BACKGROUND_ROWS = 6;
    private const int BACKGROUND_COVERS_PER_ROW = 18;

    /** Форма логина. Если пользователь уже вошёл — сразу в админку. */
    #[Route('/admin/login', name: 'admin_login', methods: ['GET', 'POST'])]
    public function login(
        AuthenticationUtils $authenticationUtils,
        GameRepository $gameRepository,
        #[CurrentUser] ?object $user,
    ): Response {
        if ($user !== null) {
            return $this->redirectToRoute('admin_dashboard');
        }

        // Каждому ряду фона — свой набор обложек, чтобы картинки по возможности не повторялись.
        $coverImagePaths = $gameRepository->findRandomCoverImagePaths(
            self::BACKGROUND_ROWS * self::BACKGROUND_COVERS_PER_ROW,
        );

        return $this->render('public/security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'coverImageRows' => array_chunk($coverImagePaths, self::BACKGROUND_COVERS_PER_ROW),
        ]);
    }

    /** Пустой обработчик — выход перехватывается файрволом (см. security.yaml). */
    #[Route('/admin/logout', name: 'admin_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Перехватывается файрволом до вызова контроллера.');
    }
}
