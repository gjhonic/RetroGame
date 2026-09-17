<?php

namespace App\Service\Plati;

use App\Service\Plati\Interfaces\RateLimiterInterface;

class UsleepRateLimiter implements RateLimiterInterface
{
    /** Реально засыпает через usleep(). */
    public function delay(int $milliseconds): void
    {
        usleep($milliseconds * 1000);
    }
}
