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
        $search = $request->query->get('q', null);
        $genreId = $request->query->get('genre', null);

        if ($genreId !== null) {
            $genreId = (int)$genreId;
        }

        $games = $gameRepo->findByFilters($search, $genreId);
        $genres = $genreRepo->findAll();

        return $this->render('frontend/index.html.twig', [
            'games' => $games,
            'genres' => $genres,
            'search' => $search,
            'selectedGenre' => $genreId,
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
            // Берем историю цен и сортируем по дате
            $history = $gameShop->getPriceHistory()->toArray();

            usort($history, function ($a, $b) {
                return $a->getUpdatedAt() <=> $b->getUpdatedAt();
            });

            foreach ($history as $entry) {
                $priceDates[] = $entry->getUpdatedAt()->format('d.m.Y H:i');
                $priceValues[] = $entry->getPrice();
            }
        }

        return $this->render('frontend/game/show.html.twig', [
            'game' => $game,
            'priceDates' => $priceDates,
            'priceValues' => $priceValues,
        ]);
    }
}
