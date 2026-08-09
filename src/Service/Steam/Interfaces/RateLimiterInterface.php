<?php

namespace App\Service\Steam\Interfaces;

/**
 * Абстракция над паузой между запросами к внешнему API — вынесена
 * отдельно, чтобы в тестах не спать по-настоящему.
 */
interface RateLimiterInterface
{
    /** Приостанавливает выполнение на указанное количество миллисекунд. */
    public function delay(int $milliseconds): void;
}
