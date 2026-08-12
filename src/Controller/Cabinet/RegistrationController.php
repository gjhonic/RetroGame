<?php

namespace App\Controller\Cabinet;

use App\Repository\GameRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Регистрация — тонкая обёртка, сама форма и запрос к /api/register — в Vue-компоненте. */
class RegistrationController extends AbstractController
{
    private const int BACKGROUND_ROWS = 6;
    private const int BACKGROUND_COVERS_PER_ROW = 18;

    /** Страница регистрации. Если пользователь уже вошёл — сразу в кабинет. */
    #[Route('/register', name: 'app_register', methods: ['GET'])]
    public function register(GameRepository $gameRepository, #[CurrentUser] ?object $user): Response
    {
        if ($user !== null) {
            return $this->redirectToRoute('cabinet_feed');
        }

        // Тот же фон, что и на странице входа (см. LoginController) — единый визуальный стиль auth-страниц.
        $coverImagePaths = $gameRepository->findRandomCoverImagePaths(
            self::BACKGROUND_ROWS * self::BACKGROUND_COVERS_PER_ROW,
        );

        return $this->render('cabinet/registration/register.html.twig', [
            'coverImageRows' => array_chunk($coverImagePaths, self::BACKGROUND_COVERS_PER_ROW),
        ]);
    }
}
