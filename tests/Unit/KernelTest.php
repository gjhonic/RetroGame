<?php

namespace App\Tests\Unit;

use App\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class KernelTest extends TestCase
{
    public function testKernelImplementsKernelInterface(): void
    {
        $kernel = new Kernel('test', true);

        $this->assertInstanceOf(KernelInterface::class, $kernel);
    }
}
