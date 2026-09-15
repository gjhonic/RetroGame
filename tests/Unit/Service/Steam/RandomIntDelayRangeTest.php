<?php

namespace App\Tests\Unit\Service\Steam;

use App\Service\Steam\RandomIntDelayRange;
use PHPUnit\Framework\TestCase;

class RandomIntDelayRangeTest extends TestCase
{
    public function testNextReturnsValueWithinRange(): void
    {
        $delayRange = new RandomIntDelayRange();

        for ($i = 0; $i < 50; ++$i) {
            $value = $delayRange->next(1000, 3000);

            self::assertGreaterThanOrEqual(1000, $value);
            self::assertLessThanOrEqual(3000, $value);
        }
    }

    public function testNextReturnsExactValueWhenMinEqualsMax(): void
    {
        $delayRange = new RandomIntDelayRange();

        self::assertSame(1500, $delayRange->next(1500, 1500));
    }
}
