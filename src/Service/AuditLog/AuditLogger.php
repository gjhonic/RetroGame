<?php

namespace App\Service\AuditLog;

use App\Entity\AuditLog;
use App\Entity\Enum\AuditLogStatus;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/** Записывает действие в журнал (audit_log) — вызывается из мест, которые нужно аудировать. */
class AuditLogger
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /** @param array<string, mixed>|null $details */
    public function log(?User $user, string $action, AuditLogStatus $status, ?array $details = null): void
    {
        $this->entityManager->persist(new AuditLog($user, $action, $status, $details));
        $this->entityManager->flush();
    }
}
