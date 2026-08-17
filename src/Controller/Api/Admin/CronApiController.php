<?php

namespace App\Controller\Api\Admin;

use App\Entity\Cron;
use App\Repository\CronRepository;
use App\Repository\CronRunRepository;
use App\Service\Cron\CronMapper;
use App\Service\Cron\CronSyncService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** JSON API справочника кронов для админки — используется Vue-компонентами Admin/CronList и Admin/CronDetail. */
#[Route('/api/admin/crons')]
#[IsGranted('ROLE_MODERATOR')]
#[OA\Tag(name: 'Admin/Crons')]
class CronApiController extends AbstractController
{
    private const string COLOR_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    /**
     * Список всех обнаруженных кронов (перед выдачей справочник синхронизируется
     * с командами, помеченными #[AsTrackedCron], см. CronSyncService) с данными
     * об их последнем запуске.
     */
    #[Route('', name: 'app_api_admin_cron_list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Список кронов',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'items', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'command', type: 'string'),
                        new OA\Property(property: 'name', type: 'string', nullable: true),
                        new OA\Property(property: 'color', type: 'string', nullable: true),
                        new OA\Property(property: 'lastRun', type: 'object', nullable: true),
                    ],
                    type: 'object',
                )),
            ],
            type: 'object',
        ),
    )]
    public function list(
        CronSyncService $cronSyncService,
        CronRepository $cronRepository,
        CronRunRepository $cronRunRepository,
        CronMapper $cronMapper,
    ): JsonResponse {
        $cronSyncService->sync();

        $items = array_map(
            static function (Cron $cron) use ($cronMapper, $cronRunRepository) {
                return $cronMapper->toListItem($cron, $cronRunRepository->findLatest($cron->getCommand()));
            },
            $cronRepository->findAllOrderedByCommand(),
        );

        return $this->json(['items' => $items]);
    }

    /** Подробности одного крона. */
    #[Route('/{id}', name: 'app_api_admin_cron_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Подробности крона')]
    #[OA\Response(response: 404, description: 'Крон не найден')]
    public function show(int $id, CronRepository $cronRepository, CronMapper $cronMapper): JsonResponse
    {
        $cron = $cronRepository->find($id);

        if ($cron === null) {
            throw $this->createNotFoundException('Крон не найден.');
        }

        return $this->json($cronMapper->toDetail($cron));
    }

    /** Задаёт название и/или цвет крона для графика — поля в теле запроса необязательны, обновляются только присланные. */
    #[Route('/{id}', name: 'app_api_admin_cron_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'name', type: 'string', nullable: true, example: 'Импорт игр из Steam'),
            new OA\Property(property: 'color', type: 'string', nullable: true, example: '#198754'),
        ],
        type: 'object',
    ))]
    #[OA\Response(response: 200, description: 'Крон обновлён')]
    #[OA\Response(response: 404, description: 'Крон не найден')]
    #[OA\Response(response: 422, description: 'Некорректный формат цвета или названия')]
    public function update(
        int $id,
        Request $request,
        CronRepository $cronRepository,
        CronMapper $cronMapper,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $cron = $cronRepository->find($id);

        if ($cron === null) {
            throw $this->createNotFoundException('Крон не найден.');
        }

        $data = json_decode($request->getContent(), true);
        $data = \is_array($data) ? $data : [];

        if (\array_key_exists('color', $data)) {
            $color = $data['color'];
            if ($color !== null && (!\is_string($color) || preg_match(self::COLOR_PATTERN, $color) !== 1)) {
                return $this->json(['errors' => ['color' => ['Цвет должен быть в формате #RRGGBB.']]], 422);
            }

            $cron->setColor($color);
        }

        if (\array_key_exists('name', $data)) {
            $name = $data['name'];
            if ($name !== null && !\is_string($name)) {
                return $this->json(['errors' => ['name' => ['Название должно быть строкой.']]], 422);
            }

            $name = \is_string($name) ? trim($name) : null;
            $cron->setName($name === '' ? null : $name);
        }

        $entityManager->flush();

        return $this->json($cronMapper->toDetail($cron));
    }
}
