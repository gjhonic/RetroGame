<?php

namespace App\Service\User;

use App\Dto\User\RegisterUserRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\User\Exceptions\EmailAlreadyRegisteredException;
use App\Service\User\Exceptions\NicknameAlreadyTakenException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Регистрирует нового пользователя по данным из публичного API. */
class UserRegistrationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * @throws EmailAlreadyRegisteredException если email уже занят
     * @throws NicknameAlreadyTakenException если ник уже занят
     */
    public function register(RegisterUserRequest $request): User
    {
        if ($request->email === '') {
            throw new \InvalidArgumentException('Email must not be empty.');
        }

        if ($this->userRepository->findOneByEmail($request->email) !== null) {
            throw new EmailAlreadyRegisteredException('Пользователь с таким email уже зарегистрирован.');
        }

        if ($this->userRepository->findOneByNickname($request->nickname) !== null) {
            throw new NicknameAlreadyTakenException('Этот ник уже занят.');
        }

        $user = new User($request->email, '');
        $user->setPassword($this->passwordHasher->hashPassword($user, $request->password));
        $user->setNickname($request->nickname);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
