<?php

namespace App\Entity\Enum;

/** Итог операции, зафиксированной в audit_log. */
enum AuditLogStatus: string
{
    case Success = 'success';
    case Failure = 'failure';
}
