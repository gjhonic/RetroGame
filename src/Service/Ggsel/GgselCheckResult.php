<?php

namespace App\Service\Ggsel;

use App\Entity\Game;
use App\Entity\GgselGame;

/**
 * Итог проверки одной игры — сама игра, найденная запись (null — не
 * нашлось) и все попытки по слагам-кандидатам в порядке перебора (см.
 * GameImportService::SLUG_SUFFIXES) — нужны для подробного лога, куда
 * именно ходили и почему не нашли (см. ImportGgselGamesCommand).
 */
final class GgselCheckResult
{
    /**
     * @param array<int, GgselSlugAttempt> $attempts
     */
    public function __construct(
        public readonly Game $game,
        public readonly ?GgselGame $ggselGame,
        public readonly array $attempts = [],
    ) {
    }

    public function isFound(): bool
    {
        return $this->ggselGame !== null;
    }
}
