<?php

namespace App\Controller\Admin;

use App\Entity\Game;
use App\Form\GameType;
use App\Repository\GameRepository;
use App\Repository\GenreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/game')]
class GameController extends AbstractController
{
    #[Route('/', name: 'admin_game_index', methods: ['GET'])]
    public function index(GameRepository $gameRepository, GenreRepository $genreRepository, Request $request): Response
    {
        $search = $request->query->get('q');
        $genreId = $request->query->get('genre');
        $page = (int) max(1, (int) $request->query->get('page', '1'));
        $limit = 20;

        $sort = $request->query->get('sort', 'steamPopularity');
        $direction = strtolower($request->query->get('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (null !== $genreId) {
            $genreId = (int) $genreId;
        }

        $games = $gameRepository->findGamesByFilters($search, $genreId, $page, $limit, null, $sort, $direction);
        $total = $gameRepository->countByFilters($search, $genreId, null);
        $totalPages = (int) ceil($total / $limit);
        $genres = $genreRepository->findAll();

        return $this->render('admin/game/index.html.twig', [
            'games' => $games,
            'genres' => $genres,
            'search' => $search,
            'selectedGenre' => $genreId,
            'sort' => $sort,
            'direction' => $direction,
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/{id}', name: 'admin_game_show', methods: ['GET'])]
    public function show(Game $game): Response
    {
        return $this->render('admin/game/show.html.twig', [
            'game' => $game,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_game_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Game $game, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(GameType::class, $game);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('admin_game_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/game/edit.html.twig', [
            'game' => $game,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_game_delete', methods: ['POST'])]
    public function delete(Request $request, Game $game, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $game->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($game);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_game_index', [], Response::HTTP_SEE_OTHER);
    }
}
