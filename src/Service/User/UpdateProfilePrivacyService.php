<?php

namespace App\Service\User;

use App\Dto\User\UpdatePrivacyRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/** Меняет видимость публичного профиля пользователя (`/profile/{nickname}`). */
class UpdateProfilePrivacyService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function update(User $user, UpdatePrivacyRequest $request): void
    {
        $user->setIsProfilePublic($request->isProfilePublic);
        $user->touch();

        $this->entityManager->flush();
    }
}
