<?php

namespace App\Service\GamePrice;

/** Итог одного прохода GamePriceCleanupService::cleanup(). */
class GamePriceCleanupResult
{
    public function __construct(
        public readonly int $deletedCount,
        public readonly int $processedGamesCount,
    ) {
    }
}
