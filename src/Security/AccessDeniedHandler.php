<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

/**
 * Для `/api/...` авторизованному, но недостаточно привилегированному пользователю
 * (например, ROLE_USER на `/api/admin/...`) отдаём JSON 403 вместо HTML-страницы
 * ошибки — по аналогии с `AuthenticationEntryPoint` для анонимных запросов.
 * Для остальных путей возвращаем `null`, чтобы Symfony обработала отказ штатно.
 */
class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            return new JsonResponse(['message' => 'Доступ запрещён.'], Response::HTTP_FORBIDDEN);
        }

        return null;
    }
}
