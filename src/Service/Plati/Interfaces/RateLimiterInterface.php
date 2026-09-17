<?php

namespace App\Service\Plati\Interfaces;

/**
 * Абстракция над паузой между запросами к api.digiseller.ru — вынесена
 * отдельно, чтобы в тестах не спать по-настоящему.
 */
interface RateLimiterInterface
{
    /** Приостанавливает выполнение на указанное количество миллисекунд. */
    public function delay(int $milliseconds): void;
}
