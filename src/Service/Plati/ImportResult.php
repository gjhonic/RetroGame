<?php

namespace App\Service\Plati;

/**
 * Итог одного запуска импорта — результат проверки каждой отдельной игры
 * (см. PlatiCheckResult), в порядке обхода — команда печатает по строке на
 * игру: нашлась или нет, и если нашлась — ссылку (см. ImportPlatiGamesCommand).
 */
final class ImportResult
{
    /**
     * @param array<int, PlatiCheckResult> $results
     */
    public function __construct(
        public readonly array $results,
        public readonly bool $wrapped = false,
    ) {
    }

    /** Сколько игр всего проверено в этой пачке. */
    public function checkedCount(): int
    {
        return count($this->results);
    }

    /** Сколько игр в пачке нашлись на plati.market. */
    public function foundCount(): int
    {
        return count(array_filter($this->results, static fn (PlatiCheckResult $result): bool => $result->isFound()));
    }
}
