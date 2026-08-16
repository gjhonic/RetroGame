<?php

namespace App\EventListener;

use App\Entity\Enum\AuditLogStatus;
use App\Service\AuditLog\AuditLogger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * Пишет неудачную попытку входа в журнал действий — событие общее для form_login
 * (веб-панель) и json_login/JWT (мобильный API), см. security.yaml. Пользователь
 * на этот момент не аутентифицирован, поэтому запись без владельца — только
 * попытавшийся email в деталях (если удалось его извлечь из запроса).
 */
#[AsEventListener(event: LoginFailureEvent::class)]
class LoginFailureListener
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function __invoke(LoginFailureEvent $event): void
    {
        $this->auditLogger->log(null, 'user.login', AuditLogStatus::Failure, [
            'email' => $this->extractEmail($event->getRequest()),
            'reason' => $event->getException()->getMessageKey(),
        ]);
    }

    private function extractEmail(Request $request): ?string
    {
        $email = $request->request->get('email');
        if (\is_string($email) && $email !== '') {
            return $email;
        }

        $payload = json_decode($request->getContent(), true);
        if (\is_array($payload) && \is_string($payload['email'] ?? null) && $payload['email'] !== '') {
            return $payload['email'];
        }

        return null;
    }
}
