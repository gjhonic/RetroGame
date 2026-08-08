<?php

namespace App\Service\Steam;

use App\Entity\Enum\SteamGameStatus;
use App\Entity\SteamGame;

/**
 * Итог одного запуска импорта/повтора — что обработали и как продолжить.
 */
final class ImportResult
{
    /**
     * Сохраняет обработанные записи и данные для продолжения импорта.
     *
     * @param array<int, SteamGame> $steamGames
     */
    public function __construct(
        public readonly array $steamGames,
        public readonly bool $hasMore = false,
        public readonly int $lastAppId = 0,
    ) {
    }

    /** Считает, сколько обработанных записей имеют указанный статус. */
    public function countByStatus(SteamGameStatus $status): int
    {
        return count(array_filter(
            $this->steamGames,
            static fn (SteamGame $steamGame): bool => $steamGame->getStatus() === $status,
        ));
    }
}
