<?php

namespace App\Controller\Public;

use App\Repository\GameRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Публичные страницы каталога игр. */
class GameController extends AbstractController
{
    private const int PER_PAGE = 24;

    /** Список игр с постраничной навигацией. */
    #[Route('/', name: 'app_game_index', methods: ['GET'])]
    public function index(Request $request, GameRepository $gameRepository): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $total = $gameRepository->count([]);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $totalPages);

        $games = $gameRepository->findBy(
            criteria: [],
            orderBy: ['name' => 'ASC'],
            limit: self::PER_PAGE,
            offset: ($page - 1) * self::PER_PAGE,
        );

        return $this->render('public/game/index.html.twig', [
            'games' => $games,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /** Карточка одной игры со всеми подробностями. */
    #[Route('/games/{slug}', name: 'app_game_show', methods: ['GET'])]
    public function show(string $slug, GameRepository $gameRepository): Response
    {
        $game = $gameRepository->findOneBy(['slug' => $slug]);

        if ($game === null) {
            throw $this->createNotFoundException('Игра не найдена.');
        }

        return $this->render('public/game/show.html.twig', [
            'game' => $game,
        ]);
    }
}
