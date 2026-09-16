<?php

namespace App\Service\Ggsel;

/** Результат успешного нахождения товара на ggsel.net. */
final class GgselProduct
{
    public function __construct(public readonly string $url)
    {
    }
}
