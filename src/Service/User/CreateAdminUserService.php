<?php

namespace App\Service\User;

use App\Entity\Enum\UserRole;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Идемпотентно создаёт (или обновляет пароль и роль) администратора по указанному email. */
class CreateAdminUserService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * Находит пользователя по email и назначает ему роль администратора и заданный пароль,
     * либо создаёт нового администратора, если такого email ещё нет.
     *
     * @param non-empty-string $email
     */
    public function createOrUpdate(string $email, string $password): User
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if ($user === null) {
            $user = new User($email, '', UserRole::Admin);
            $this->entityManager->persist($user);
        } else {
            $user->setRole(UserRole::Admin);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->touch();

        $this->entityManager->flush();

        return $user;
    }
}
