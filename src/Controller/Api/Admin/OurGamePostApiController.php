<?php

namespace App\Controller\Api\Admin;

use App\Entity\User;
use App\Repository\OurGamePostRepository;
use App\Service\OurGamePost\Exceptions\OurGameNotFoundException;
use App\Service\OurGamePost\OurGamePostCrudService;
use App\Service\OurGamePost\OurGamePostImageUploadService;
use App\Service\OurGamePost\OurGamePostMapper;
use App\Service\OurGamePost\OurGamePostRequestFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** JSON API постов о своих играх для админки — используется Vue-компонентами Admin/OurGamePost*. */
#[Route('/api/admin/our-game-posts')]
#[IsGranted('ROLE_MODERATOR')]
#[OA\Tag(name: 'Admin/OurGamePosts')]
class OurGamePostApiController extends AbstractController
{
    private const int DEFAULT_PER_PAGE = 25;
    private const int MAX_PER_PAGE = 100;

    /** Колонки, по которым разрешена сортировка (см. OurGamePostRepository::applyAdminSort()). */
    private const array SORTABLE_FIELDS = ['postedAt', 'type', 'status', 'game'];

    /** Колонки, по которым разрешена фильтрация (query-параметр filters[<ключ>]). */
    private const array FILTERABLE_FIELDS = ['game', 'type', 'status'];

    /**
     * Страница списка постов для таблицы в админке: фильтры, сортировка
     * и постраничная навигация выполняются в БД.
     */
    #[Route('', name: 'app_api_admin_our_game_post_list', methods: ['GET'])]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer', default: 25))]
    #[OA\Parameter(name: 'filters[game]', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'filters[type]', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'filters[status]', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'Поле сортировки: postedAt, type, status, game',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: 'postedAt'),
    )]
    #[OA\Parameter(name: 'sortDir', in: 'query', schema: new OA\Schema(type: 'string', default: 'desc'))]
    #[OA\Response(response: 200, description: 'Страница списка постов с постраничной навигацией')]
    public function list(
        Request $request,
        OurGamePostRepository $ourGamePostRepository,
        OurGamePostMapper $ourGamePostMapper,
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

        $sortBy = $request->query->getString('sortBy', 'postedAt');
        $sortField = \in_array($sortBy, self::SORTABLE_FIELDS, true) ? $sortBy : 'postedAt';
        $sortDir = strtolower($request->query->getString('sortDir', 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $total = $ourGamePostRepository->countForAdminList($filters);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);

        $posts = $ourGamePostRepository->findForAdminList(
            $filters,
            $sortField,
            $sortDir,
            $perPage,
            ($page - 1) * $perPage,
        );

        return $this->json([
            'items' => array_map($ourGamePostMapper->toAdminListItem(...), $posts),
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /** Создаёт новый пост — автором становится текущий пользователь. */
    #[Route('', name: 'app_api_admin_our_game_post_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Пост создан')]
    #[OA\Response(response: 422, description: 'Ошибки валидации')]
    public function create(
        Request $request,
        OurGamePostRequestFactory $requestFactory,
        OurGamePostCrudService $ourGamePostCrudService,
        OurGamePostMapper $ourGamePostMapper,
        #[CurrentUser] User $user,
    ): JsonResponse {
        [$dto, $errors] = $requestFactory->fromJson($request->getContent());
        if ($dto === null) {
            return $this->json(['errors' => $errors], 422);
        }

        try {
            $post = $ourGamePostCrudService->create($user, $dto);
        } catch (OurGameNotFoundException $exception) {
            return $this->json(['errors' => ['gameId' => [$exception->getMessage()]]], 422);
        }

        return $this->json($ourGamePostMapper->toDetail($post), 201);
    }

    /** Подробности одного поста. */
    #[Route('/{id}', name: 'app_api_admin_our_game_post_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Подробности поста')]
    #[OA\Response(response: 404, description: 'Пост не найден')]
    public function show(
        int $id,
        OurGamePostRepository $ourGamePostRepository,
        OurGamePostMapper $ourGamePostMapper,
    ): JsonResponse {
        $post = $ourGamePostRepository->find($id);

        if ($post === null) {
            throw $this->createNotFoundException('Пост не найден.');
        }

        return $this->json($ourGamePostMapper->toDetail($post));
    }

    /** Обновляет пост — форма всегда шлёт полный набор полей. */
    #[Route('/{id}', name: 'app_api_admin_our_game_post_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Пост обновлён')]
    #[OA\Response(response: 404, description: 'Пост не найден')]
    #[OA\Response(response: 422, description: 'Ошибки валидации')]
    public function update(
        int $id,
        Request $request,
        OurGamePostRequestFactory $requestFactory,
        OurGamePostRepository $ourGamePostRepository,
        OurGamePostCrudService $ourGamePostCrudService,
        OurGamePostMapper $ourGamePostMapper,
    ): JsonResponse {
        $post = $ourGamePostRepository->find($id);
        if ($post === null) {
            throw $this->createNotFoundException('Пост не найден.');
        }

        [$dto, $errors] = $requestFactory->fromJson($request->getContent());
        if ($dto === null) {
            return $this->json(['errors' => $errors], 422);
        }

        try {
            $post = $ourGamePostCrudService->update($post, $dto);
        } catch (OurGameNotFoundException $exception) {
            return $this->json(['errors' => ['gameId' => [$exception->getMessage()]]], 422);
        }

        return $this->json($ourGamePostMapper->toDetail($post));
    }

    /** Удаляет пост вместе с его картинкой. */
    #[Route('/{id}', name: 'app_api_admin_our_game_post_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 204, description: 'Пост удалён')]
    #[OA\Response(response: 404, description: 'Пост не найден')]
    public function delete(
        int $id,
        OurGamePostRepository $ourGamePostRepository,
        OurGamePostCrudService $ourGamePostCrudService,
    ): JsonResponse {
        $post = $ourGamePostRepository->find($id);
        if ($post === null) {
            throw $this->createNotFoundException('Пост не найден.');
        }

        $ourGamePostCrudService->delete($post);

        return $this->json(null, 204);
    }

    /** Загружает вертикальную картинку поста. */
    #[Route(
        '/{id}/image',
        name: 'app_api_admin_our_game_post_upload_image',
        methods: ['POST'],
        requirements: ['id' => '\d+'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Картинка загружена')]
    #[OA\Response(response: 404, description: 'Пост не найден')]
    #[OA\Response(response: 422, description: 'Файл не передан')]
    public function uploadImage(
        int $id,
        Request $request,
        OurGamePostRepository $ourGamePostRepository,
        OurGamePostImageUploadService $imageUploadService,
        OurGamePostMapper $ourGamePostMapper,
    ): JsonResponse {
        $post = $ourGamePostRepository->find($id);
        if ($post === null) {
            throw $this->createNotFoundException('Пост не найден.');
        }

        $file = $request->files->get('file');
        if ($file === null || !$file->isValid()) {
            return $this->json(['errors' => ['file' => [$file?->getErrorMessage() ?? 'Файл не передан.']]], 422);
        }

        $post = $imageUploadService->upload($post, $file);

        return $this->json($ourGamePostMapper->toDetail($post));
    }

    /** Загружает картинку, вставляемую в текст поста через редактор (Admin/RichTextEditor.vue). */
    #[Route(
        '/{id}/content-images',
        name: 'app_api_admin_our_game_post_upload_content_image',
        methods: ['POST'],
        requirements: ['id' => '\d+'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Картинка загружена',
        content: new OA\JsonContent(properties: [new OA\Property(property: 'url', type: 'string')], type: 'object'),
    )]
    #[OA\Response(response: 404, description: 'Пост не найден')]
    #[OA\Response(response: 422, description: 'Файл не передан')]
    public function uploadContentImage(
        int $id,
        Request $request,
        OurGamePostRepository $ourGamePostRepository,
        OurGamePostImageUploadService $imageUploadService,
    ): JsonResponse {
        $post = $ourGamePostRepository->find($id);
        if ($post === null) {
            throw $this->createNotFoundException('Пост не найден.');
        }

        $file = $request->files->get('file');
        if ($file === null || !$file->isValid()) {
            return $this->json(['errors' => ['file' => [$file?->getErrorMessage() ?? 'Файл не передан.']]], 422);
        }

        $relativePath = $imageUploadService->uploadContentImage($post, $file);

        return $this->json(['url' => '/' . $relativePath]);
    }
}
