<?php

namespace App\Service\User;

use App\Dto\User\CreateModeratorRequest;
use App\Entity\Enum\UserRole;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\User\Exceptions\EmailAlreadyRegisteredException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Создаёт аккаунт модератора из админки — доступно только ROLE_ADMIN (см. UserApiController::createModerator()). */
class ModeratorCreationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /** @throws EmailAlreadyRegisteredException если email уже занят */
    public function create(CreateModeratorRequest $request): User
    {
        if ($request->email === '') {
            throw new \InvalidArgumentException('Email must not be empty.');
        }

        if ($this->userRepository->findOneByEmail($request->email) !== null) {
            throw new EmailAlreadyRegisteredException('Пользователь с таким email уже зарегистрирован.');
        }

        $user = new User($request->email, '', UserRole::Moderator);
        $user->setPassword($this->passwordHasher->hashPassword($user, $request->password));
        $user->setNickname($request->nickname);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
