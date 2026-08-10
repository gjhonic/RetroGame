<?php

namespace App\Service\User;

use App\Entity\User;

/** Маппинг сущности User в массивы для JSON API. */
class UserMapper
{
    /** @return array<string, mixed> */
    public function toPublic(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'nickname' => $user->getNickname(),
            'avatarUrl' => $user->getAvatarUrl(),
            'role' => $user->getRole()->value,
            'createdAt' => $user->getCreatedAt()->format('Y-m-d\TH:i:sP'),
        ];
    }
}
