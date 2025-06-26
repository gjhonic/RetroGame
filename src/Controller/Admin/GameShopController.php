<?php

namespace App\Controller\Admin;

use App\Entity\GameShop;
use App\Form\GameShopType;
use App\Repository\GameShopRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/game-shop')]
class GameShopController extends AbstractController
{
    #[Route('/', name: 'app_game_shop_index', methods: ['GET'])]
    public function index(GameShopRepository $gameShopRepository): Response
    {
        return $this->render('game_shop/index.html.twig', [
            'game_shops' => $gameShopRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_game_shop_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $gameShop = new GameShop();
        $form = $this->createForm(GameShopType::class, $gameShop);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($gameShop);
            $entityManager->flush();

            return $this->redirectToRoute('app_game_shop_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('game_shop/new.html.twig', [
            'game_shop' => $gameShop,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_game_shop_show', methods: ['GET'])]
    public function show(GameShop $gameShop): Response
    {
        return $this->render('game_shop/show.html.twig', [
            'game_shop' => $gameShop,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_game_shop_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, GameShop $gameShop, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(GameShopType::class, $gameShop);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_game_shop_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('game_shop/edit.html.twig', [
            'game_shop' => $gameShop,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_game_shop_delete', methods: ['POST'])]
    public function delete(Request $request, GameShop $gameShop, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $gameShop->getId(), (string)$request->request->get('_token'))) {
            $entityManager->remove($gameShop);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_game_shop_index', [], Response::HTTP_SEE_OTHER);
    }
}
