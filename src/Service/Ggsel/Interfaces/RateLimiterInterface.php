<?php

namespace App\Service\Ggsel\Interfaces;

/**
 * Абстракция над паузой между запросами к ggsel.net — вынесена отдельно,
 * чтобы в тестах не спать по-настоящему.
 */
interface RateLimiterInterface
{
    /** Приостанавливает выполнение на указанное количество миллисекунд. */
    public function delay(int $milliseconds): void;
}
