<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Страницы справочника кронов в админке — тонкие Twig-обёртки, данные
 * подгружает Vue-компонент через /api/admin/crons (см. Api\Admin\CronApiController).
 */
#[IsGranted('ROLE_MODERATOR')]
class CronController extends AbstractController
{
    /** Список кронов. */
    #[Route('/admin/crons', name: 'admin_cron_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/cron/index.html.twig');
    }

    /** Карточка одного крона с последними запусками. */
    #[Route('/admin/crons/{id}', name: 'admin_cron_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        return $this->render('admin/cron/show.html.twig', [
            'id' => $id,
        ]);
    }
}
