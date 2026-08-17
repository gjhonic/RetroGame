<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Страница журнала действий в админке — тонкая Twig-обёртка, данные подгружает
 * Vue-компонент через /api/admin/audit-logs (см. Api\Admin\AuditLogApiController).
 * Доступна только ROLE_ADMIN — строже, чем остальная админка (ROLE_MODERATOR).
 */
#[IsGranted('ROLE_ADMIN')]
class AuditLogController extends AbstractController
{
    /** Список записей журнала действий. */
    #[Route('/admin/audit-logs', name: 'admin_audit_log_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/audit_log/index.html.twig');
    }
}
