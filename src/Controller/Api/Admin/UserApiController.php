<?php

namespace App\Controller\Api\Admin;

use App\Dto\User\CreateModeratorRequest;
use App\Repository\UserRepository;
use App\Service\User\Exceptions\EmailAlreadyRegisteredException;
use App\Service\User\ModeratorCreationService;
use App\Service\User\UserMapper;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** JSON API пользователей для админки — используется Vue-компонентами Admin/UserList, Admin/UserDetail. */
#[Route('/api/admin/users')]
#[IsGranted('ROLE_MODERATOR')]
#[OA\Tag(name: 'Admin/Users')]
class UserApiController extends AbstractController
{
    private const int DEFAULT_PER_PAGE = 25;
    private const int MAX_PER_PAGE = 100;

    /** Колонки, по которым разрешена сортировка (см. UserRepository::applyAdminSort()). */
    private const array SORTABLE_FIELDS = ['email', 'nickname', 'role', 'createdAt', 'lastLoginAt'];

    /** Колонки, по которым разрешена фильтрация (query-параметр filters[<ключ>]). */
    private const array FILTERABLE_FIELDS = ['email', 'nickname', 'role'];

    /**
     * Страница списка пользователей для таблицы в админке (TanStack Table):
     * фильтры, сортировка и постраничная навигация выполняются в БД.
     */
    #[Route('', name: 'app_api_admin_user_list', methods: ['GET'])]
    #[OA\Parameter(
        name: 'page',
        description: 'Номер страницы (по умолчанию 1)',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1),
    )]
    #[OA\Parameter(
        name: 'perPage',
        description: 'Строк на странице (по умолчанию 25, максимум 100)',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 25),
    )]
    #[OA\Parameter(
        name: 'filters[email]',
        description: 'Фильтр по email (подстрока)',
        in: 'query',
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Parameter(
        name: 'filters[nickname]',
        description: 'Фильтр по нику (подстрока)',
        in: 'query',
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Parameter(
        name: 'filters[role]',
        description: 'Фильтр по роли: ROLE_USER, ROLE_MODERATOR, ROLE_ADMIN',
        in: 'query',
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'Поле сортировки: email, nickname, role, createdAt, lastLoginAt',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: 'email'),
    )]
    #[OA\Parameter(
        name: 'sortDir',
        description: 'Направление сортировки: asc, desc',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: 'asc'),
    )]
    #[OA\Response(
        response: 200,
        description: 'Страница списка пользователей с постраничной навигацией',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'items', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', nullable: true),
                        new OA\Property(property: 'email', type: 'string'),
                        new OA\Property(property: 'nickname', type: 'string', nullable: true),
                        new OA\Property(property: 'role', type: 'string'),
                        new OA\Property(property: 'createdAt', type: 'string'),
                        new OA\Property(property: 'lastLoginAt', type: 'string', nullable: true),
                    ],
                    type: 'object',
                )),
                new OA\Property(property: 'total', type: 'integer'),
                new OA\Property(property: 'page', type: 'integer'),
                new OA\Property(property: 'totalPages', type: 'integer'),
            ],
            type: 'object',
        ),
    )]
    public function list(Request $request, UserRepository $userRepository, UserMapper $userMapper): JsonResponse
    {
        $perPage = max(1, min(self::MAX_PER_PAGE, $request->query->getInt('perPage', self::DEFAULT_PER_PAGE)));

        $rawFilters = $request->query->all('filters');
        $filters = [];
        foreach (self::FILTERABLE_FIELDS as $field) {
            $value = $rawFilters[$field] ?? null;
            if (\is_string($value) && trim($value) !== '') {
                $filters[$field] = trim($value);
            }
        }

        $sortBy = $request->query->getString('sortBy', 'email');
        $sortField = \in_array($sortBy, self::SORTABLE_FIELDS, true) ? $sortBy : 'email';
        $sortDir = strtolower($request->query->getString('sortDir', 'asc')) === 'desc' ? 'DESC' : 'ASC';

        $total = $userRepository->countForAdminList($filters);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);

        $users = $userRepository->findForAdminList($filters, $sortField, $sortDir, $perPage, ($page - 1) * $perPage);

        return $this->json([
            'items' => array_map($userMapper->toAdminListItem(...), $users),
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /** Подробности одного пользователя. */
    #[Route('/{id}', name: 'app_api_admin_user_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Parameter(
        name: 'id',
        description: 'ID пользователя',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Response(response: 200, description: 'Подробности пользователя')]
    #[OA\Response(response: 404, description: 'Пользователь не найден')]
    public function show(int $id, UserRepository $userRepository, UserMapper $userMapper): JsonResponse
    {
        $user = $userRepository->find($id);

        if ($user === null) {
            throw $this->createNotFoundException('Пользователь не найден.');
        }

        return $this->json($userMapper->toDetail($user));
    }

    /**
     * Создаёт аккаунт модератора — сознательно доступно только ROLE_ADMIN
     * (более строгое требование, чем у остального модуля), чтобы обычные
     * модераторы не могли выдавать права модератора друг другу.
     */
    #[Route('/moderators', name: 'app_api_admin_user_create_moderator', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\RequestBody(content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'email', type: 'string'),
            new OA\Property(property: 'password', type: 'string'),
            new OA\Property(property: 'nickname', type: 'string'),
        ],
        type: 'object',
    ))]
    #[OA\Response(response: 201, description: 'Модератор создан')]
    #[OA\Response(response: 422, description: 'Ошибки валидации')]
    #[OA\Response(response: 409, description: 'Email уже занят')]
    public function createModerator(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ModeratorCreationService $moderatorCreationService,
        UserMapper $userMapper,
    ): JsonResponse {
        try {
            $dto = $serializer->deserialize($request->getContent(), CreateModeratorRequest::class, 'json');
        } catch (SerializerExceptionInterface) {
            return $this->json(['message' => 'Некорректное тело запроса.'], 400);
        }

        $violations = $validator->validate($dto);
        if (\count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()][] = $violation->getMessage();
            }

            return $this->json(['errors' => $errors], 422);
        }

        try {
            $user = $moderatorCreationService->create($dto);
        } catch (EmailAlreadyRegisteredException $exception) {
            throw new ConflictHttpException($exception->getMessage());
        }

        return $this->json($userMapper->toDetail($user), 201);
    }
}
