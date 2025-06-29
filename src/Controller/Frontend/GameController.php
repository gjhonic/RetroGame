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

        $games = $gameRepo->findGamesByFilters($search, $genreId, $page, $limit);
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

        $shopsWithPrices = [];
        $gameCharts = [];

        foreach ($game->getShops() as $gameShop) {
            $shop = $gameShop->getShop();
            $shopId = $shop?->getId();
            if (!$shopId) {
                continue;
            }

            // Получаем историю цен и сортируем по дате
            $history = $gameShop->getPriceHistory()->toArray();

            usort($history, function ($a, $b) {
                return $a->getUpdatedAt() <=> $b->getUpdatedAt();
            });

            if (empty($history)) {
                continue;
            }

            $gameChart = [
                'id' => $shopId,
                'name' => $shop->getName(),
                'priceDates' => [],
                'priceValues' => [],
            ];

            // Собираем данные по датам и ценам
            foreach ($history as $entry) {
                $gameChart['priceDates'][] = $entry->getUpdatedAt()->format('d.m.Y H:i');
                $gameChart['priceValues'][] = $entry->getPrice();
            }

            $gameCharts[] = $gameChart;

            // Добавляем в список только магазины с данными
            $shopsWithPrices[] = $gameShop;
        }

        return $this->render('frontend/game/show.html.twig', [
            'game' => $game,
            'shopsWithPrices' => $shopsWithPrices, // Только магазины с данными
            'gameCharts' => $gameCharts,
        ]);
    }
}
