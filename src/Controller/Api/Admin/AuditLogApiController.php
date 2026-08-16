<?php

namespace App\Controller\Api\Admin;

use App\Repository\AuditLogRepository;
use App\Service\AuditLog\AuditLogMapper;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** JSON API журнала действий — используется Vue-компонентом Admin/AuditLogList. */
#[Route('/api/admin/audit-logs')]
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: 'Admin/AuditLogs')]
class AuditLogApiController extends AbstractController
{
    private const int DEFAULT_PER_PAGE = 25;
    private const int MAX_PER_PAGE = 100;

    /** Список записей журнала с фильтрами, диапазоном дат, сортировкой и постраничной навигацией. */
    #[Route('', name: 'app_api_admin_audit_log_list', methods: ['GET'])]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer', default: 25))]
    #[OA\Parameter(name: 'filters[action]', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'filters[status]', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(
        name: 'filters[user]',
        description: 'ID пользователя',
        in: 'query',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Parameter(
        name: 'dateFrom',
        description: 'Начало диапазона по createdAt (ISO 8601)',
        in: 'query',
        schema: new OA\Schema(type: 'string', format: 'date-time'),
    )]
    #[OA\Parameter(
        name: 'dateTo',
        description: 'Конец диапазона по createdAt (ISO 8601)',
        in: 'query',
        schema: new OA\Schema(type: 'string', format: 'date-time'),
    )]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'createdAt, action, status',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: 'createdAt'),
    )]
    #[OA\Parameter(name: 'sortDir', in: 'query', schema: new OA\Schema(type: 'string', default: 'desc'))]
    #[OA\Response(response: 200, description: 'Страница журнала действий с постраничной навигацией')]
    public function list(Request $request, AuditLogRepository $auditLogRepository, AuditLogMapper $mapper): JsonResponse
    {
        $perPage = max(1, min(self::MAX_PER_PAGE, $request->query->getInt('perPage', self::DEFAULT_PER_PAGE)));

        $rawFilters = $request->query->all('filters');
        $filters = [];
        foreach (['action', 'status', 'user'] as $field) {
            $value = $rawFilters[$field] ?? null;
            if (\is_string($value) && trim($value) !== '') {
                $filters[$field] = trim($value);
            }
        }

        $dateFromRaw = $request->query->getString('dateFrom', '');
        $dateFrom = $dateFromRaw !== '' ? new \DateTimeImmutable($dateFromRaw) : null;
        $dateToRaw = $request->query->getString('dateTo', '');
        $dateTo = $dateToRaw !== '' ? new \DateTimeImmutable($dateToRaw) : null;

        $sortBy = $request->query->getString('sortBy', 'createdAt');
        $sortField = \in_array($sortBy, AuditLogRepository::SORTABLE_FIELDS, true) ? $sortBy : 'createdAt';
        $sortDir = strtolower($request->query->getString('sortDir', 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $total = $auditLogRepository->countForAdminList($filters, $dateFrom, $dateTo);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);

        $logs = $auditLogRepository->findForAdminList(
            $filters,
            $dateFrom,
            $dateTo,
            $sortField,
            $sortDir,
            $perPage,
            ($page - 1) * $perPage,
        );

        return $this->json([
            'items' => array_map($mapper->toListItem(...), $logs),
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /** Уникальные значения action, для которых есть хотя бы одна запись — для фильтра в админке. */
    #[Route('/actions', name: 'app_api_admin_audit_log_actions', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Список значений action')]
    public function actions(AuditLogRepository $auditLogRepository): JsonResponse
    {
        return $this->json(['actions' => $auditLogRepository->findDistinctActions()]);
    }

    /** Подробности одной записи журнала, включая произвольный JSON. */
    #[Route('/{id}', name: 'app_api_admin_audit_log_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Подробности записи журнала')]
    #[OA\Response(response: 404, description: 'Запись не найдена')]
    public function show(int $id, AuditLogRepository $auditLogRepository, AuditLogMapper $mapper): JsonResponse
    {
        $auditLog = $auditLogRepository->find($id);
        if ($auditLog === null) {
            throw $this->createNotFoundException('Запись журнала не найдена.');
        }

        return $this->json($mapper->toDetail($auditLog));
    }
}
