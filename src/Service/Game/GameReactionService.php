<?php

namespace App\Service\Game;

use App\Entity\Enum\GameReactionType;
use App\Entity\Game;
use App\Entity\GameReaction;
use App\Entity\User;
use App\Repository\GameReactionRepository;
use Doctrine\ORM\EntityManagerInterface;

/** Ставит/меняет/снимает реакцию (лайк/дизлайк) пользователя на игру — один голос на игру. */
class GameReactionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GameReactionRepository $gameReactionRepository,
    ) {
    }

    /**
     * Идемпотентный upsert: если голоса ещё нет — создаёт, если есть — меняет
     * тип. Один PUT-эндпоинт покрывает и "поставить", и "сменить" голос.
     */
    public function setReaction(Game $game, User $user, GameReactionType $type): GameReaction
    {
        $reaction = $this->gameReactionRepository->findOneByGameAndUser($game, $user);

        if ($reaction === null) {
            $reaction = new GameReaction($game, $user, $type);
            $this->entityManager->persist($reaction);
        } else {
            $reaction->setType($type)->touch();
        }

        $this->entityManager->flush();

        return $reaction;
    }

    /** Снимает голос, если он есть; если голоса нет — no-op (идемпотентно). */
    public function removeReaction(Game $game, User $user): void
    {
        $reaction = $this->gameReactionRepository->findOneByGameAndUser($game, $user);

        if ($reaction === null) {
            return;
        }

        $this->entityManager->remove($reaction);
        $this->entityManager->flush();
    }
}
