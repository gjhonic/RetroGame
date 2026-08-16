<?php

namespace App\Service\User;

use App\Dto\User\UpdateNicknameRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\User\Exceptions\NicknameAlreadyTakenException;
use Doctrine\ORM\EntityManagerInterface;

/** Задаёт или меняет ник пользователя — нужен для публичного профиля (`/profile/{nickname}`). */
class UpdateNicknameService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
    ) {
    }

    /** @throws NicknameAlreadyTakenException если ник уже занят другим пользователем */
    public function update(User $user, UpdateNicknameRequest $request): void
    {
        $nickname = trim($request->nickname);

        $existing = $this->userRepository->findOneByNickname($nickname);
        if ($existing !== null && $existing->getId() !== $user->getId()) {
            throw new NicknameAlreadyTakenException('Этот ник уже занят.');
        }

        $user->setNickname($nickname);
        $user->touch();

        $this->entityManager->flush();
    }
}
