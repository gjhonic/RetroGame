<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Страница учёта кронов в админке — тонкая Twig-обёртка, данные подгружает
 * Vue-компонент через /api/admin/cron-runs (см. Api\Admin\CronRunApiController).
 */
#[IsGranted('ROLE_MODERATOR')]
class CronRunController extends AbstractController
{
    /** Список запусков кронов с таймлайном. */
    #[Route('/admin/cron-runs', name: 'admin_cron_run_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/cron_run/index.html.twig');
    }
}
