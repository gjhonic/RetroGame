<?php

namespace App\Service\Game;

use App\Entity\Enum\GamePlaythroughStatus;
use App\Entity\Game;
use App\Entity\GameStatus;
use App\Entity\User;
use App\Repository\GameStatusRepository;
use Doctrine\ORM\EntityManagerInterface;

/** Ставит/меняет/снимает статус прохождения игры пользователем — один статус на игру. */
class GameStatusService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GameStatusRepository $gameStatusRepository,
    ) {
    }

    /** Идемпотентный upsert: если статуса ещё нет — создаёт, если есть — меняет. */
    public function setStatus(Game $game, User $user, GamePlaythroughStatus $status): GameStatus
    {
        $gameStatus = $this->gameStatusRepository->findOneByGameAndUser($game, $user);

        if ($gameStatus === null) {
            $gameStatus = new GameStatus($game, $user, $status);
            $this->entityManager->persist($gameStatus);
        } else {
            $gameStatus->setStatus($status)->touch();
        }

        $this->entityManager->flush();

        return $gameStatus;
    }

    /** Снимает статус, если он есть; если статуса нет — no-op (идемпотентно). */
    public function removeStatus(Game $game, User $user): void
    {
        $gameStatus = $this->gameStatusRepository->findOneByGameAndUser($game, $user);

        if ($gameStatus === null) {
            return;
        }

        $this->entityManager->remove($gameStatus);
        $this->entityManager->flush();
    }
}
