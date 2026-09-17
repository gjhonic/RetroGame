<?php

namespace App\Controller\Cabinet;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Политика сайта (юридический disclaimer) — тонкая Twig-обёртка со статичным текстом, без данных из БД. */
class PolicyController extends AbstractController
{
    #[Route('/policy', name: 'app_policy', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('cabinet/policy/index.html.twig');
    }
}
