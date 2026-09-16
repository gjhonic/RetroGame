<?php

namespace App\Service\Ggsel;

/**
 * Итог попытки найти товар по одному конкретному слагу — для подробного
 * лога (см. ImportGgselGamesCommand): какой URL запрашивали, что нашли, и
 * если не нашли — почему (HTTP-статус или причина, по которой страница не
 * распознана как страница товара).
 */
final class GgselSlugAttempt
{
    public function __construct(
        public readonly string $url,
        public readonly ?GgselProduct $product,
        public readonly string $reason,
    ) {
    }

    public function isFound(): bool
    {
        return $this->product !== null;
    }
}
