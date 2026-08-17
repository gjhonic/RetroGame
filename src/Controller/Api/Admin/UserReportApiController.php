<?php

namespace App\Controller\Api\Admin;

use App\Repository\UserReportRepository;
use App\Service\UserReport\UserReportMapper;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** JSON API списка отчётов пользователей — используется Vue-компонентом Admin/UserReportList. */
#[Route('/api/admin/user-reports')]
#[OA\Tag(name: 'Admin/UserReports')]
class UserReportApiController extends AbstractController
{
    private const int DEFAULT_PER_PAGE = 25;
    private const int MAX_PER_PAGE = 100;

    /** Список отчётов с фильтром по типу, сортировкой и постраничной навигацией. */
    #[Route('', name: 'app_api_admin_user_report_list', methods: ['GET'])]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer', default: 25))]
    #[OA\Parameter(
        name: 'filters[type]',
        description: '1 — сайт, 2 — мобильное приложение, 3 — игра DIE//AGAIN',
        in: 'query',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'createdAt, type',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: 'createdAt'),
    )]
    #[OA\Parameter(name: 'sortDir', in: 'query', schema: new OA\Schema(type: 'string', default: 'desc'))]
    #[OA\Response(response: 200, description: 'Страница отчётов пользователей с постраничной навигацией')]
    public function list(
        Request $request,
        UserReportRepository $userReportRepository,
        UserReportMapper $mapper,
    ): JsonResponse {
        $perPage = max(1, min(self::MAX_PER_PAGE, $request->query->getInt('perPage', self::DEFAULT_PER_PAGE)));

        $rawFilters = $request->query->all('filters');
        $filters = [];
        $type = $rawFilters['type'] ?? null;
        if (\is_string($type) && trim($type) !== '') {
            $filters['type'] = trim($type);
        }

        $sortBy = $request->query->getString('sortBy', 'createdAt');
        $sortField = \in_array($sortBy, UserReportRepository::SORTABLE_FIELDS, true) ? $sortBy : 'createdAt';
        $sortDir = strtolower($request->query->getString('sortDir', 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $total = $userReportRepository->countForAdminList($filters);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);

        $reports = $userReportRepository->findForAdminList(
            $filters,
            $sortField,
            $sortDir,
            $perPage,
            ($page - 1) * $perPage,
        );

        return $this->json([
            'items' => array_map($mapper->toListItem(...), $reports),
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }
}
