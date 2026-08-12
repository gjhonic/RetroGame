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

    /** @return array<string, mixed> */
    public function toAdminListItem(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'nickname' => $user->getNickname(),
            'role' => $user->getRole()->value,
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'lastLoginAt' => $user->getLastLoginAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public function toDetail(User $user): array
    {
        return [
            ...$this->toAdminListItem($user),
            'avatarUrl' => $user->getAvatarUrl(),
            'updatedAt' => $user->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
