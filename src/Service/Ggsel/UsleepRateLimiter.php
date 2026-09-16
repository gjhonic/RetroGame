<?php

namespace App\Service\Ggsel;

use App\Service\Ggsel\Interfaces\RateLimiterInterface;

class UsleepRateLimiter implements RateLimiterInterface
{
    /** Реально засыпает через usleep(). */
    public function delay(int $milliseconds): void
    {
        usleep($milliseconds * 1000);
    }
}
