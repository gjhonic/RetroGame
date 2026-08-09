<?php

namespace App\Controller\Api\Admin;

use App\Repository\CronRunRepository;
use App\Service\Cron\CronLogReader;
use App\Service\Cron\CronRunMapper;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** JSON API учёта запусков кронов — используется Vue-компонентом Admin/CronRunList. */
#[Route('/api/admin/cron-runs')]
#[IsGranted('ROLE_MODERATOR')]
#[OA\Tag(name: 'Admin/CronRuns')]
class CronRunApiController extends AbstractController
{
    private const int DEFAULT_PER_PAGE = 25;
    private const int MAX_PER_PAGE = 100;

    /** Список запусков с фильтрами, диапазоном дат, сортировкой и постраничной навигацией. */
    #[Route('', name: 'app_api_admin_cron_run_list', methods: ['GET'])]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer', default: 25))]
    #[OA\Parameter(name: 'filters[command]', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'filters[status]', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(
        name: 'dateFrom',
        description: 'Начало диапазона по startedAt (ISO 8601)',
        in: 'query',
        schema: new OA\Schema(type: 'string', format: 'date-time'),
    )]
    #[OA\Parameter(
        name: 'dateTo',
        description: 'Конец диапазона по startedAt (ISO 8601)',
        in: 'query',
        schema: new OA\Schema(type: 'string', format: 'date-time'),
    )]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'startedAt, command, durationMs, memoryPeakBytes, status',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: 'startedAt'),
    )]
    #[OA\Parameter(name: 'sortDir', in: 'query', schema: new OA\Schema(type: 'string', default: 'desc'))]
    #[OA\Response(response: 200, description: 'Страница списка запусков с постраничной навигацией')]
    public function list(Request $request, CronRunRepository $cronRunRepository, CronRunMapper $mapper): JsonResponse
    {
        $perPage = max(1, min(self::MAX_PER_PAGE, $request->query->getInt('perPage', self::DEFAULT_PER_PAGE)));

        $rawFilters = $request->query->all('filters');
        $filters = [];
        foreach (['command', 'status'] as $field) {
            $value = $rawFilters[$field] ?? null;
            if (\is_string($value) && trim($value) !== '') {
                $filters[$field] = trim($value);
            }
        }

        $dateFromRaw = $request->query->getString('dateFrom', '');
        $dateFrom = $dateFromRaw !== '' ? new \DateTimeImmutable($dateFromRaw) : null;
        $dateToRaw = $request->query->getString('dateTo', '');
        $dateTo = $dateToRaw !== '' ? new \DateTimeImmutable($dateToRaw) : null;

        $sortBy = $request->query->getString('sortBy', 'startedAt');
        $sortField = \in_array($sortBy, CronRunRepository::SORTABLE_FIELDS, true) ? $sortBy : 'startedAt';
        $sortDir = strtolower($request->query->getString('sortDir', 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $total = $cronRunRepository->countForAdminList($filters, $dateFrom, $dateTo);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);

        $runs = $cronRunRepository->findForAdminList(
            $filters,
            $dateFrom,
            $dateTo,
            $sortField,
            $sortDir,
            $perPage,
            ($page - 1) * $perPage,
        );

        return $this->json([
            'items' => array_map($mapper->toListItem(...), $runs),
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /** Имена всех когда-либо запускавшихся команд — для фильтра в админке. */
    #[Route('/commands', name: 'app_api_admin_cron_run_commands', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Список имён команд')]
    public function commands(CronRunRepository $cronRunRepository): JsonResponse
    {
        return $this->json(['commands' => $cronRunRepository->findDistinctCommands()]);
    }

    /** Данные для таймлайна/графика: запуски за тот же диапазон дат, что и в таблице. */
    #[Route('/timeline', name: 'app_api_admin_cron_run_timeline', methods: ['GET'])]
    #[OA\Parameter(
        name: 'dateFrom',
        description: 'Начало диапазона по startedAt (ISO 8601), по умолчанию — сутки назад',
        in: 'query',
        schema: new OA\Schema(type: 'string', format: 'date-time'),
    )]
    #[OA\Parameter(
        name: 'dateTo',
        description: 'Конец диапазона по startedAt (ISO 8601), по умолчанию — сейчас',
        in: 'query',
        schema: new OA\Schema(type: 'string', format: 'date-time'),
    )]
    #[OA\Response(response: 200, description: 'Список запусков за период')]
    public function timeline(
        Request $request,
        CronRunRepository $cronRunRepository,
        CronRunMapper $mapper,
    ): JsonResponse {
        $dateFromRaw = $request->query->getString('dateFrom', '');
        $dateFrom = $dateFromRaw !== ''
            ? new \DateTimeImmutable($dateFromRaw)
            : (new \DateTimeImmutable())->modify('-24 hours');
        $dateToRaw = $request->query->getString('dateTo', '');
        $dateTo = $dateToRaw !== '' ? new \DateTimeImmutable($dateToRaw) : new \DateTimeImmutable();

        $runs = $cronRunRepository->findForTimeline($dateFrom, $dateTo);

        return $this->json(['items' => array_map($mapper->toListItem(...), $runs)]);
    }

    /** Подробности одного запуска. */
    #[Route('/{id}', name: 'app_api_admin_cron_run_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Подробности запуска')]
    #[OA\Response(response: 404, description: 'Запуск не найден')]
    public function show(int $id, CronRunRepository $cronRunRepository, CronRunMapper $mapper): JsonResponse
    {
        $cronRun = $cronRunRepository->find($id);

        if ($cronRun === null) {
            throw $this->createNotFoundException('Запуск не найден.');
        }

        return $this->json($mapper->toDetail($cronRun));
    }

    /** Полный текст лог-файла запуска — при ?download=1 отдаётся как файл для скачивания. */
    #[Route('/{id}/log', name: 'app_api_admin_cron_run_log', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(
        name: 'download',
        description: 'Если 1 — ответ с Content-Disposition: attachment',
        in: 'query',
        schema: new OA\Schema(type: 'boolean'),
    )]
    #[OA\Response(response: 200, description: 'Текст лога', content: new OA\MediaType(mediaType: 'text/plain'))]
    #[OA\Response(response: 404, description: 'Запуск или лог не найдены')]
    public function log(
        int $id,
        Request $request,
        CronRunRepository $cronRunRepository,
        CronLogReader $cronLogReader,
    ): Response {
        $cronRun = $cronRunRepository->find($id);

        if ($cronRun === null) {
            throw $this->createNotFoundException('Запуск не найден.');
        }

        $content = $cronLogReader->read($cronRun);

        if ($content === null) {
            throw $this->createNotFoundException('Лог не найден.');
        }

        $headers = ['Content-Type' => 'text/plain; charset=UTF-8'];
        if ($request->query->getBoolean('download')) {
            $filename = sprintf('%s-%d.log', str_replace(':', '_', $cronRun->getCommand()), $id);
            $headers['Content-Disposition'] = HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $filename,
            );
        }

        return new Response($content, Response::HTTP_OK, $headers);
    }
}
