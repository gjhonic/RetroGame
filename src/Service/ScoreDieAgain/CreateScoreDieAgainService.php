<?php

namespace App\Service\ScoreDieAgain;

use App\Dto\ScoreDieAgain\CreateScoreDieAgainRequest;
use App\Entity\ScoreDieAgain;
use Doctrine\ORM\EntityManagerInterface;

/** Сохраняет результат раунда игры DIE//AGAIN. */
class CreateScoreDieAgainService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function create(CreateScoreDieAgainRequest $request): ScoreDieAgain
    {
        $score = new ScoreDieAgain(
            trim($request->nickname),
            $request->level,
            $request->survivedSeconds,
            $request->kills,
        );
        $this->entityManager->persist($score);
        $this->entityManager->flush();

        return $score;
    }
}
