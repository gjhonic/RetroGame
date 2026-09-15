<?php

namespace App\Service\Steam;

use App\Service\Steam\Interfaces\RandomDelayRangeInterface;

class RandomIntDelayRange implements RandomDelayRangeInterface
{
    /** Реально возвращает случайное число через random_int(). */
    public function next(int $minMilliseconds, int $maxMilliseconds): int
    {
        return random_int($minMilliseconds, $maxMilliseconds);
    }
}
