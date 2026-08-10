<?php

namespace App\Service\User;

use App\Dto\User\ChangePasswordRequest;
use App\Entity\User;
use App\Service\User\Exceptions\InvalidCurrentPasswordException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Меняет пароль пользователя из личного кабинета. */
class ChangePasswordService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /** @throws InvalidCurrentPasswordException если текущий пароль указан неверно */
    public function changePassword(User $user, ChangePasswordRequest $request): void
    {
        if (!$this->passwordHasher->isPasswordValid($user, $request->currentPassword)) {
            throw new InvalidCurrentPasswordException('Неверный текущий пароль.');
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $request->newPassword));
        $user->touch();

        $this->entityManager->flush();
    }
}
