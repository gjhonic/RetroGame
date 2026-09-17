<?php

namespace App\Service\Plati;

use App\Entity\Game;
use App\Entity\PlatiGame;

/**
 * Итог проверки одной игры — сама игра, найденные записи (пустой массив —
 * не нашлось ни одного продавца, см. класс-докблок PlatiGame про лимит в
 * несколько продавцов на игру) и человекочитаемая причина (сколько
 * результатов вернул поиск, сколько из них совпало по названию, что
 * выбрано, либо текст ошибки) — для подробного лога (см.
 * ImportPlatiGamesCommand).
 */
final class PlatiCheckResult
{
    /**
     * @param array<int, PlatiGame> $platiGames
     */
    public function __construct(
        public readonly Game $game,
        public readonly array $platiGames,
        public readonly string $reason,
    ) {
    }

    public function isFound(): bool
    {
        return $this->platiGames !== [];
    }
}
