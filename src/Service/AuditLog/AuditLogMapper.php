<?php

namespace App\Service\AuditLog;

use App\Entity\AuditLog;

/** Маппинг сущности AuditLog в массивы для JSON API. */
class AuditLogMapper
{
    /** @return array<string, mixed> */
    public function toListItem(AuditLog $auditLog): array
    {
        $user = $auditLog->getUser();

        return [
            'id' => $auditLog->getId(),
            'user' => $user !== null ? ['id' => $user->getId(), 'email' => $user->getEmail()] : null,
            'action' => $auditLog->getAction(),
            'status' => $auditLog->getStatus()->value,
            'createdAt' => $auditLog->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public function toDetail(AuditLog $auditLog): array
    {
        return [
            ...$this->toListItem($auditLog),
            'details' => $auditLog->getDetails(),
        ];
    }
}
