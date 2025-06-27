<?php

// src/Controller/Frontend/GameController.php

namespace App\Controller\Frontend;

use App\Repository\GameRepository;
use App\Repository\GenreRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GameController extends AbstractController
{
    #[Route('/', name: 'frontend_index')]
    public function index(Request $request, GameRepository $gameRepo, GenreRepository $genreRepo): Response
    {
        $search = $request->query->get('q');
        $genreId = $request->query->get('genre');
        $page = (int)max(1, (int)$request->query->get('page', '1'));
        $limit = 40;

        if ($genreId !== null) {
            $genreId = (int)$genreId;
        }

        $games = $gameRepo->findByFilters($search, $genreId, $page, $limit);
        $total = $gameRepo->countByFilters($search, $genreId);
        $totalPages = (int) ceil($total / $limit);

        $genres = $genreRepo->findAll();

        return $this->render('frontend/index.html.twig', [
            'games' => $games,
            'genres' => $genres,
            'search' => $search,
            'selectedGenre' => $genreId,
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/game/{id}', name: 'frontend_game_show')]
    public function show(int $id, GameRepository $gameRepo): Response
    {
        $game = $gameRepo->find($id);
        if (!$game) {
            throw $this->createNotFoundException('Game not found');
        }

        // Собираем данные по всем магазинам
        $priceDates = [];
        $priceValues = [];

        foreach ($game->getShops() as $gameShop) {
            $shopId = $gameShop->getShop()?->getId();
            $history = $gameShop->getPriceHistory()->toArray();

            usort($history, function ($a, $b) {
                return $a->getUpdatedAt() <=> $b->getUpdatedAt();
            });

            foreach ($history as $entry) {
                $priceDates[$shopId][] = $entry->getUpdatedAt()->format('d.m.Y H:i');
                $priceValues[$shopId][] = $entry->getPrice();
            }
        }

        return $this->render('frontend/game/show.html.twig', [
            'game' => $game,
            'priceDates' => $priceDates,
            'priceValues' => $priceValues,
        ]);
    }
}
