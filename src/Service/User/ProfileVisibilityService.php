<?php

namespace App\Service\User;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\User\Exceptions\ProfileNotFoundException;

/**
 * Резолвит владельца публичного профиля по нику: сам владелец видит свой профиль всегда,
 * остальные — только если профиль открыт. Не различает "не найден" и "закрыт" в тексте
 * исключения — по умолчанию не палит существование закрытых профилей.
 */
class ProfileVisibilityService
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    /** @throws ProfileNotFoundException если пользователь не найден или его профиль закрыт */
    public function resolveVisibleUser(string $nickname, ?User $viewer): User
    {
        $user = $this->userRepository->findOneByNickname($nickname);
        if ($user === null) {
            throw new ProfileNotFoundException('Профиль не найден.');
        }

        $isOwner = $viewer !== null && $viewer->getId() !== null && $viewer->getId() === $user->getId();
        if (!$user->isProfilePublic() && !$isOwner) {
            throw new ProfileNotFoundException('Профиль не найден.');
        }

        return $user;
    }
}
