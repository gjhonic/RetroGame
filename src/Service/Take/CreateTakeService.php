<?php

namespace App\Service\Take;

use App\Dto\Take\CreateTakeRequest;
use App\Entity\Take;
use App\Entity\User;
use App\Repository\GameRepository;
use App\Service\Take\Exceptions\GameNotFoundException;
use Doctrine\ORM\EntityManagerInterface;

/** Создаёт тэйк текущего пользователя об игре. */
class CreateTakeService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GameRepository $gameRepository,
    ) {
    }

    /** @throws GameNotFoundException если игра с указанным gameId не найдена */
    public function create(User $author, CreateTakeRequest $request): Take
    {
        $game = $this->gameRepository->find($request->gameId);
        if ($game === null) {
            throw new GameNotFoundException('Игра не найдена.');
        }

        $take = new Take($author, $game, $request->text);
        $this->entityManager->persist($take);
        $this->entityManager->flush();

        return $take;
    }
}
