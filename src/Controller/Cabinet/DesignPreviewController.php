<?php

namespace App\Controller\Cabinet;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Совместимость со ссылками на утверждённый прототип Street Play. */
class DesignPreviewController extends AbstractController
{
    #[Route('/design-preview/games', name: 'design_preview_games', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('app_game_index');
    }

    #[Route('/design-preview/games/{slug}', name: 'design_preview_game', methods: ['GET'])]
    public function show(string $slug): Response
    {
        return $this->redirectToRoute('app_game_show', ['slug' => $slug]);
    }
}
