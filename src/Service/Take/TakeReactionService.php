<?php

namespace App\Service\Take;

use App\Entity\Enum\TakeReactionType;
use App\Entity\Take;
use App\Entity\TakeReaction;
use App\Entity\User;
use App\Repository\TakeReactionRepository;
use Doctrine\ORM\EntityManagerInterface;

/** Ставит/меняет/снимает реакцию (лайк/дизлайк) пользователя на тэйк — один голос на тэйк. */
class TakeReactionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TakeReactionRepository $takeReactionRepository,
    ) {
    }

    /**
     * Идемпотентный upsert: если голоса ещё нет — создаёт, если есть — меняет
     * тип. Один PUT-эндпоинт покрывает и "поставить", и "сменить" голос.
     */
    public function setReaction(Take $take, User $user, TakeReactionType $type): TakeReaction
    {
        $reaction = $this->takeReactionRepository->findOneByTakeAndUser($take, $user);

        if ($reaction === null) {
            $reaction = new TakeReaction($take, $user, $type);
            $this->entityManager->persist($reaction);
        } else {
            $reaction->setType($type)->touch();
        }

        $this->entityManager->flush();

        return $reaction;
    }

    /** Снимает голос, если он есть; если голоса нет — no-op (идемпотентно). */
    public function removeReaction(Take $take, User $user): void
    {
        $reaction = $this->takeReactionRepository->findOneByTakeAndUser($take, $user);

        if ($reaction === null) {
            return;
        }

        $this->entityManager->remove($reaction);
        $this->entityManager->flush();
    }
}
