<?php

namespace App\Service\Steam\Interfaces;

/**
 * Абстракция над случайной величиной паузы — вынесена отдельно, чтобы в
 * тестах случайность была детерминированной (аналогично RateLimiterInterface,
 * который вынесен, чтобы в тестах не спать по-настоящему).
 */
interface RandomDelayRangeInterface
{
    /** Возвращает случайное значение паузы в миллисекундах из диапазона [$minMilliseconds, $maxMilliseconds]. */
    public function next(int $minMilliseconds, int $maxMilliseconds): int;
}
