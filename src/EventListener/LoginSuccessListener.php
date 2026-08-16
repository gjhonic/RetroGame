<?php

namespace App\EventListener;

use App\Entity\Enum\AuditLogStatus;
use App\Entity\User;
use App\Service\AuditLog\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Обновляет lastLoginAt и пишет запись в журнал действий при успешном входе —
 * событие общее для form_login (веб-панель) и json_login/JWT (мобильный API),
 * см. security.yaml.
 */
#[AsEventListener(event: LoginSuccessEvent::class)]
class LoginSuccessListener
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $user->touchLastLogin();
        $this->entityManager->flush();

        $this->auditLogger->log($user, 'user.login', AuditLogStatus::Success);
    }
}
