<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Единый формат ошибок для `/api/...`: `{"message": "..."}` вместо HTML-страницы
 * Symfony по умолчанию — так же, как уже отдают 401/403 `AuthenticationEntryPoint`
 * и `AccessDeniedHandler`. Приоритет выше, чем у стандартного `ErrorListener`
 * (-128), чтобы успеть подменить ответ до рендера HTML; если ответ уже выставлен
 * (например, тем же security-слушателем для 401/403) — не трогаем его.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -8)]
class ApiExceptionListener
{
    public function __construct(private readonly bool $debug)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if ($event->hasResponse() || !str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            $message = $exception->getMessage() !== '' ? $exception->getMessage() : Response::$statusTexts[$status];

            $event->setResponse(new JsonResponse(['message' => $message], $status, $exception->getHeaders()));

            return;
        }

        $status = Response::HTTP_INTERNAL_SERVER_ERROR;
        $message = $this->debug ? $exception->getMessage() : 'Внутренняя ошибка сервера.';

        $event->setResponse(new JsonResponse(['message' => $message], $status));
    }
}
