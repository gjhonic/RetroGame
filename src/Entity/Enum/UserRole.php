<?php

namespace App\Entity\Enum;

enum UserRole: string
{
    case User = 'ROLE_USER';
    case Moderator = 'ROLE_MODERATOR';
    case Admin = 'ROLE_ADMIN';
}
