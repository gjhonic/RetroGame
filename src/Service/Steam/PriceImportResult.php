<?php

namespace App\Service\Steam;

use App\Entity\GamePrice;

/**
 * Итог одного запуска пачечного импорта цен — что обработали и как продолжить.
 */
final class PriceImportResult
{
    /**
     * @param array<int, GamePrice> $prices
     */
    public function __construct(
        public readonly array $prices,
        public readonly int $skippedCount = 0,
        public readonly int $lastSteamGameId = 0,
        public readonly bool $startedNewDay = false,
        public readonly ?int $lastPopularity = null,
    ) {
    }

    /** Сколько игр в пачке оказались бесплатными. */
    public function countFree(): int
    {
        return count(array_filter($this->prices, static fn (GamePrice $price): bool => $price->isFree()));
    }

    /** Сколько игр в пачке недоступны для покупки в российском Steam. */
    public function countUnavailable(): int
    {
        return count(
            array_filter($this->prices, static fn (GamePrice $price): bool => !$price->isAvailableInRussia()),
        );
    }

    /** Сколько игр в пачке получили платную цену. */
    public function countPriced(): int
    {
        return count(array_filter(
            $this->prices,
            static fn (GamePrice $price): bool => $price->isAvailableInRussia() && !$price->isFree(),
        ));
    }
}
