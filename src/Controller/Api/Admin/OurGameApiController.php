<?php

namespace App\Controller\Api\Admin;

use App\Repository\OurGameRepository;
use App\Service\OurGame\OurGameCrudService;
use App\Service\OurGame\OurGameImageUploadService;
use App\Service\OurGame\OurGameMapper;
use App\Service\OurGame\OurGameRequestFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** JSON API своих игр для админки — используется Vue-компонентами Admin/OurGameList, Admin/OurGameDetail. */
#[Route('/api/admin/our-games')]
#[IsGranted('ROLE_MODERATOR')]
#[OA\Tag(name: 'Admin/OurGames')]
class OurGameApiController extends AbstractController
{
    private const int DEFAULT_PER_PAGE = 25;
    private const int MAX_PER_PAGE = 100;

    /** Колонки, по которым разрешена сортировка (см. OurGameRepository::applyAdminSort()). */
    private const array SORTABLE_FIELDS = ['name', 'status', 'releaseDate', 'createdAt'];

    /** Колонки, по которым разрешена фильтрация (query-параметр filters[<ключ>]). */
    private const array FILTERABLE_FIELDS = ['name', 'status', 'genre'];

    /**
     * Страница списка своих игр для таблицы в админке: фильтры, сортировка
     * и постраничная навигация выполняются в БД.
     */
    #[Route('', name: 'app_api_admin_our_game_list', methods: ['GET'])]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer', default: 25))]
    #[OA\Parameter(name: 'filters[name]', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'filters[status]', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'filters[genre]', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'Поле сортировки: name, status, releaseDate, createdAt',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: 'name'),
    )]
    #[OA\Parameter(name: 'sortDir', in: 'query', schema: new OA\Schema(type: 'string', default: 'asc'))]
    #[OA\Response(response: 200, description: 'Страница списка своих игр с постраничной навигацией')]
    public function list(
        Request $request,
        OurGameRepository $ourGameRepository,
        OurGameMapper $ourGameMapper,
    ): JsonResponse {
        $perPage = max(1, min(self::MAX_PER_PAGE, $request->query->getInt('perPage', self::DEFAULT_PER_PAGE)));

        $rawFilters = $request->query->all('filters');
        $filters = [];
        foreach (self::FILTERABLE_FIELDS as $field) {
            $value = $rawFilters[$field] ?? null;
            if (\is_string($value) && trim($value) !== '') {
                $filters[$field] = trim($value);
            }
        }

        $sortBy = $request->query->getString('sortBy', 'name');
        $sortField = \in_array($sortBy, self::SORTABLE_FIELDS, true) ? $sortBy : 'name';
        $sortDir = strtolower($request->query->getString('sortDir', 'asc')) === 'desc' ? 'DESC' : 'ASC';

        $total = $ourGameRepository->countForAdminList($filters);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);

        $games = $ourGameRepository->findForAdminList($filters, $sortField, $sortDir, $perPage, ($page - 1) * $perPage);

        return $this->json([
            'items' => array_map($ourGameMapper->toAdminListItem(...), $games),
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /** Создаёт новую игру. */
    #[Route('', name: 'app_api_admin_our_game_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Игра создана')]
    #[OA\Response(response: 422, description: 'Ошибки валидации')]
    public function create(
        Request $request,
        OurGameRequestFactory $requestFactory,
        OurGameCrudService $ourGameCrudService,
        OurGameMapper $ourGameMapper,
    ): JsonResponse {
        [$dto, $errors] = $requestFactory->fromJson($request->getContent());
        if ($dto === null) {
            return $this->json(['errors' => $errors], 422);
        }

        $game = $ourGameCrudService->create($dto);

        return $this->json($ourGameMapper->toDetail($game), 201);
    }

    /** Подробности одной игры. */
    #[Route('/{id}', name: 'app_api_admin_our_game_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Подробности игры')]
    #[OA\Response(response: 404, description: 'Игра не найдена')]
    public function show(int $id, OurGameRepository $ourGameRepository, OurGameMapper $ourGameMapper): JsonResponse
    {
        $game = $ourGameRepository->find($id);

        if ($game === null) {
            throw $this->createNotFoundException('Игра не найдена.');
        }

        return $this->json($ourGameMapper->toDetail($game));
    }

    /** Обновляет игру — форма всегда шлёт полный набор полей. */
    #[Route('/{id}', name: 'app_api_admin_our_game_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Игра обновлена')]
    #[OA\Response(response: 404, description: 'Игра не найдена')]
    #[OA\Response(response: 422, description: 'Ошибки валидации')]
    public function update(
        int $id,
        Request $request,
        OurGameRequestFactory $requestFactory,
        OurGameRepository $ourGameRepository,
        OurGameCrudService $ourGameCrudService,
        OurGameMapper $ourGameMapper,
    ): JsonResponse {
        $game = $ourGameRepository->find($id);
        if ($game === null) {
            throw $this->createNotFoundException('Игра не найдена.');
        }

        [$dto, $errors] = $requestFactory->fromJson($request->getContent());
        if ($dto === null) {
            return $this->json(['errors' => $errors], 422);
        }

        $game = $ourGameCrudService->update($game, $dto);

        return $this->json($ourGameMapper->toDetail($game));
    }

    /** Удаляет игру вместе со всеми её картинками и ссылками на скачивание. */
    #[Route('/{id}', name: 'app_api_admin_our_game_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 204, description: 'Игра удалена')]
    #[OA\Response(response: 404, description: 'Игра не найдена')]
    public function delete(
        int $id,
        OurGameRepository $ourGameRepository,
        OurGameCrudService $ourGameCrudService,
    ): JsonResponse {
        $game = $ourGameRepository->find($id);
        if ($game === null) {
            throw $this->createNotFoundException('Игра не найдена.');
        }

        $ourGameCrudService->delete($game);

        return $this->json(null, 204);
    }

    /** Загружает основную обложку. */
    #[Route(
        '/{id}/cover',
        name: 'app_api_admin_our_game_upload_cover',
        methods: ['POST'],
        requirements: ['id' => '\d+'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Обложка загружена')]
    #[OA\Response(response: 404, description: 'Игра не найдена')]
    #[OA\Response(response: 422, description: 'Файл не передан')]
    public function uploadCover(
        int $id,
        Request $request,
        OurGameRepository $ourGameRepository,
        OurGameImageUploadService $imageUploadService,
        OurGameMapper $ourGameMapper,
    ): JsonResponse {
        $game = $ourGameRepository->find($id);
        if ($game === null) {
            throw $this->createNotFoundException('Игра не найдена.');
        }

        $file = $request->files->get('file');
        if ($file === null || !$file->isValid()) {
            return $this->json(['errors' => ['file' => [$file?->getErrorMessage() ?? 'Файл не передан.']]], 422);
        }

        $game = $imageUploadService->uploadCover($game, $file);

        return $this->json($ourGameMapper->toDetail($game));
    }

    /** Загружает баннер. */
    #[Route(
        '/{id}/banner',
        name: 'app_api_admin_our_game_upload_banner',
        methods: ['POST'],
        requirements: ['id' => '\d+'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Баннер загружен')]
    #[OA\Response(response: 404, description: 'Игра не найдена')]
    #[OA\Response(response: 422, description: 'Файл не передан')]
    public function uploadBanner(
        int $id,
        Request $request,
        OurGameRepository $ourGameRepository,
        OurGameImageUploadService $imageUploadService,
        OurGameMapper $ourGameMapper,
    ): JsonResponse {
        $game = $ourGameRepository->find($id);
        if ($game === null) {
            throw $this->createNotFoundException('Игра не найдена.');
        }

        $file = $request->files->get('file');
        if ($file === null || !$file->isValid()) {
            return $this->json(['errors' => ['file' => [$file?->getErrorMessage() ?? 'Файл не передан.']]], 422);
        }

        $game = $imageUploadService->uploadBanner($game, $file);

        return $this->json($ourGameMapper->toDetail($game));
    }

    /** Добавляет скриншот. */
    #[Route(
        '/{id}/screenshots',
        name: 'app_api_admin_our_game_add_screenshot',
        methods: ['POST'],
        requirements: ['id' => '\d+'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Скриншот добавлен')]
    #[OA\Response(response: 404, description: 'Игра не найдена')]
    #[OA\Response(response: 422, description: 'Файл не передан')]
    public function addScreenshot(
        int $id,
        Request $request,
        OurGameRepository $ourGameRepository,
        OurGameImageUploadService $imageUploadService,
        OurGameMapper $ourGameMapper,
    ): JsonResponse {
        $game = $ourGameRepository->find($id);
        if ($game === null) {
            throw $this->createNotFoundException('Игра не найдена.');
        }

        $file = $request->files->get('file');
        if ($file === null || !$file->isValid()) {
            return $this->json(['errors' => ['file' => [$file?->getErrorMessage() ?? 'Файл не передан.']]], 422);
        }

        $game = $imageUploadService->addScreenshot($game, $file);

        return $this->json($ourGameMapper->toDetail($game));
    }

    /** Загружает картинку, вставляемую в описание игры через редактор (Admin/RichTextEditor.vue). */
    #[Route(
        '/{id}/content-images',
        name: 'app_api_admin_our_game_upload_content_image',
        methods: ['POST'],
        requirements: ['id' => '\d+'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Картинка загружена',
        content: new OA\JsonContent(properties: [new OA\Property(property: 'url', type: 'string')], type: 'object'),
    )]
    #[OA\Response(response: 404, description: 'Игра не найдена')]
    #[OA\Response(response: 422, description: 'Файл не передан')]
    public function uploadContentImage(
        int $id,
        Request $request,
        OurGameRepository $ourGameRepository,
        OurGameImageUploadService $imageUploadService,
    ): JsonResponse {
        $game = $ourGameRepository->find($id);
        if ($game === null) {
            throw $this->createNotFoundException('Игра не найдена.');
        }

        $file = $request->files->get('file');
        if ($file === null || !$file->isValid()) {
            return $this->json(['errors' => ['file' => [$file?->getErrorMessage() ?? 'Файл не передан.']]], 422);
        }

        $relativePath = $imageUploadService->uploadContentImage($game, $file);

        return $this->json(['url' => '/' . $relativePath]);
    }

    /** Удаляет скриншот по его URL. */
    #[Route(
        '/{id}/screenshots',
        name: 'app_api_admin_our_game_remove_screenshot',
        methods: ['DELETE'],
        requirements: ['id' => '\d+'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(content: new OA\JsonContent(
        properties: [new OA\Property(property: 'url', type: 'string')],
        type: 'object',
    ))]
    #[OA\Response(response: 200, description: 'Скриншот удалён')]
    #[OA\Response(response: 404, description: 'Игра не найдена')]
    public function removeScreenshot(
        int $id,
        Request $request,
        OurGameRepository $ourGameRepository,
        OurGameImageUploadService $imageUploadService,
        OurGameMapper $ourGameMapper,
    ): JsonResponse {
        $game = $ourGameRepository->find($id);
        if ($game === null) {
            throw $this->createNotFoundException('Игра не найдена.');
        }

        $data = json_decode($request->getContent(), true);
        $url = \is_array($data) && \is_string($data['url'] ?? null) ? $data['url'] : '';

        $game = $imageUploadService->removeScreenshot($game, $url);

        return $this->json($ourGameMapper->toDetail($game));
    }
}
