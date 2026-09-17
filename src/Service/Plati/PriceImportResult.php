<?php

namespace App\Service\Plati;

use App\Entity\PlatiGamePrice;

/**
 * Итог одного запуска пачечного импорта цен plati.market — что обработали
 * и как продолжить.
 */
final class PriceImportResult
{
    /**
     * @param array<int, PlatiGamePrice> $prices
     */
    public function __construct(
        public readonly array $prices,
        public readonly int $skippedCount = 0,
        public readonly int $lastPlatiGameId = 0,
        public readonly bool $startedNewDay = false,
        public readonly ?int $lastPopularity = null,
    ) {
    }

    /** Сколько игр в пачке получили цену. */
    public function countFound(): int
    {
        return count(array_filter($this->prices, static fn (PlatiGamePrice $price): bool => $price->isFound()));
    }

    /** Сколько игр в пачке повторным поиском не нашли. */
    public function countNotFound(): int
    {
        return count(array_filter($this->prices, static fn (PlatiGamePrice $price): bool => !$price->isFound()));
    }
}
