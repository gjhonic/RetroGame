<?php

namespace App\Service\Steam;

use App\Service\Steam\Interfaces\RateLimiterInterface;

class UsleepRateLimiter implements RateLimiterInterface
{
    /** Реально засыпает через usleep(). */
    public function delay(int $milliseconds): void
    {
        usleep($milliseconds * 1000);
    }
}
