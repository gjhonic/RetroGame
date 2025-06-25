<?php

namespace App\Controller\Admin;

use App\Entity\Genre;
use App\Form\GenreType;
use App\Repository\GenreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/genre')]
class GenreController extends AbstractController
{
    #[Route('/', name: 'admin_genre_index', methods: ['GET'])]
    public function index(GenreRepository $genreRepository): Response
    {
        $genres = $genreRepository->findAllOrderedByName();

        return $this->render('admin/genre/index.html.twig', [
            'genres' => $genres,
        ]);
    }

    #[Route('/new', name: 'admin_genre_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $genre = new Genre();
        $form = $this->createForm(GenreType::class, $genre);
        $form->handleRequest($request);
        if ($this->getUser() != null) {
            $genre->setCreatedBy($this->getUser()->getUserIdentifier());
            $genre->setUpdatedBy($this->getUser()->getUserIdentifier());
        }


        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($genre);
            $entityManager->flush();

            return $this->redirectToRoute('admin_genre_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/genre/new.html.twig', [
            'genre' => $genre,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_genre_show', methods: ['GET'])]
    public function show(Genre $genre): Response
    {
        return $this->render('admin/genre/show.html.twig', [
            'genre' => $genre,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_genre_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Genre $genre, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(GenreType::class, $genre);
        $form->handleRequest($request);
        if ($this->getUser() != null) {
            $genre->setUpdatedBy($this->getUser()->getUserIdentifier());
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('admin_genre_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/genre/edit.html.twig', [
            'genre' => $genre,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_genre_delete', methods: ['POST'])]
    public function delete(Request $request, Genre $genre, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $genre->getId(), (string)$request->request->get('_token'))) {
            $entityManager->remove($genre);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_genre_index', [], Response::HTTP_SEE_OTHER);
    }
}
