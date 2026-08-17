<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Страница отчётов пользователей в админке — тонкая Twig-обёртка, данные подгружает
 * Vue-компонент через /api/admin/user-reports (см. Api\Admin\UserReportApiController).
 */
class UserReportController extends AbstractController
{
    /** Список отчётов пользователей о проблемах. */
    #[Route('/admin/user-reports', name: 'admin_user_report_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/user_report/index.html.twig');
    }
}
