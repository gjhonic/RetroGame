<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Для `/api/...` анонимному пользователю отдаём JSON 401 вместо редиректа
 * на страницу входа: form_login по умолчанию редиректит через
 * `HttpUtils::generateUri()`, который передаёт в `UrlGenerator::generate()`
 * все атрибуты запроса — начиная с Symfony 8.1 в их числе `_controller_attributes`
 * (все PHP-атрибуты контроллера, включая `OA\Tag`), что валит `UrlGenerator`
 * ошибкой о циклической ссылке. Для остальных страниц — обычный редирект на `/login`.
 */
class AuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            return new JsonResponse(['message' => 'Требуется авторизация.'], Response::HTTP_UNAUTHORIZED);
        }

        return new RedirectResponse($this->urlGenerator->generate('login'));
    }
}
