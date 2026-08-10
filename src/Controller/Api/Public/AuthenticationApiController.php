<?php

namespace App\Controller\Api\Public;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Только для документации Swagger: сам запрос перехватывает и обрабатывает
 * firewall `api_login` (json_login, см. security.yaml) раньше, чем дошёл бы
 * до диспетчера контроллеров — тело этого метода никогда не выполняется.
 */
#[OA\Tag(name: 'Registration')]
class AuthenticationApiController extends AbstractController
{
    /** Проверяет email и пароль, выдаёт JWT-токен для доступа к /api/cabinet и /api/admin. */
    #[Route('/api/login', name: 'app_api_login', methods: ['POST'])]
    #[OA\RequestBody(content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'email', type: 'string'),
            new OA\Property(property: 'password', type: 'string'),
        ],
        type: 'object',
    ))]
    #[OA\Response(
        response: 200,
        description: 'Успешный вход — JWT-токен для заголовка Authorization: Bearer <token>',
        content: new OA\JsonContent(properties: [new OA\Property(property: 'token', type: 'string')], type: 'object'),
    )]
    #[OA\Response(response: 401, description: 'Неверный email или пароль')]
    public function loginCheck(): JsonResponse
    {
        throw new \LogicException(
            'Не должен вызываться: запрос перехватывает firewall "api_login" из security.yaml.',
        );
    }
}
