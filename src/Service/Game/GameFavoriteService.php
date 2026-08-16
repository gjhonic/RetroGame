<?php

namespace App\Service\Game;

use App\Entity\Game;
use App\Entity\GameFavorite;
use App\Entity\User;
use App\Repository\GameFavoriteRepository;
use Doctrine\ORM\EntityManagerInterface;

/** Добавляет/убирает игру из избранного пользователя — идемпотентно. */
class GameFavoriteService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GameFavoriteRepository $gameFavoriteRepository,
    ) {
    }

    /** Добавляет игру в избранное; если уже добавлена — no-op (идемпотентно). */
    public function addFavorite(Game $game, User $user): GameFavorite
    {
        $favorite = $this->gameFavoriteRepository->findOneByGameAndUser($game, $user);

        if ($favorite !== null) {
            return $favorite;
        }

        $favorite = new GameFavorite($game, $user);
        $this->entityManager->persist($favorite);
        $this->entityManager->flush();

        return $favorite;
    }

    /** Убирает игру из избранного, если она там есть; если нет — no-op (идемпотентно). */
    public function removeFavorite(Game $game, User $user): void
    {
        $favorite = $this->gameFavoriteRepository->findOneByGameAndUser($game, $user);

        if ($favorite === null) {
            return;
        }

        $this->entityManager->remove($favorite);
        $this->entityManager->flush();
    }
}
