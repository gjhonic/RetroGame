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
            'isProfilePublic' => $user->isProfilePublic(),
        ];
    }

    /**
     * Данные для публичной страницы профиля (`/profile/{nickname}`) — без email.
     * `$isFollowing` — `null`, если смотрящий не авторизован или смотрит свой профиль
     * (кнопка "Подписаться" в этих случаях не показывается).
     *
     * @return array<string, mixed>
     */
    public function toPublicProfile(
        User $user,
        int $followersCount,
        int $followingCount,
        bool $isOwnProfile,
        ?bool $isFollowing,
    ): array {
        return [
            'nickname' => $user->getNickname(),
            'avatarUrl' => $user->getAvatarUrl(),
            'createdAt' => $user->getCreatedAt()->format('Y-m-d\TH:i:sP'),
            'followersCount' => $followersCount,
            'followingCount' => $followingCount,
            'isOwnProfile' => $isOwnProfile,
            'isFollowing' => $isFollowing,
        ];
    }

    /**
     * Краткая карточка пользователя для списков (например, подписчиков) — только
     * ник и аватар, без email.
     *
     * @return array<string, mixed>
     */
    public function toProfileSummary(User $user): array
    {
        return [
            'nickname' => $user->getNickname(),
            'avatarUrl' => $user->getAvatarUrl(),
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
