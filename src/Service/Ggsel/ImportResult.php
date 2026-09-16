<?php

namespace App\Service\Ggsel;

/**
 * Итог одного запуска импорта — результат проверки каждой отдельной игры
 * (см. GgselCheckResult), в порядке обхода — команда печатает по строке на
 * игру: нашлась или нет, и если нашлась — ссылку (см. ImportGgselGamesCommand).
 */
final class ImportResult
{
    /**
     * @param array<int, GgselCheckResult> $results
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

    /** Сколько игр в пачке нашлись на ggsel.net. */
    public function foundCount(): int
    {
        return count(array_filter($this->results, static fn (GgselCheckResult $result): bool => $result->isFound()));
    }
}
