<?php

namespace App\Service\ScoreDieAgain;

use App\Entity\ScoreDieAgain;

/** Маппинг сущности ScoreDieAgain в массив для JSON API. */
class ScoreDieAgainMapper
{
    /** @return array<string, mixed> */
    public function toListItem(ScoreDieAgain $score): array
    {
        return [
            'id' => $score->getId(),
            'nickname' => $score->getNickname(),
            'level' => $score->getLevel(),
            'survivedSeconds' => $score->getSurvivedSeconds(),
            'kills' => $score->getKills(),
            'createdAt' => $score->getCreatedAt()->format('Y-m-d\TH:i:sP'),
        ];
    }
}
