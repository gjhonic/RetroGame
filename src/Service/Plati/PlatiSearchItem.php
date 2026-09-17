<?php

namespace App\Service\Plati;

/**
 * Одно объявление продавца из ответа api.digiseller.ru/api/products/search2.
 * priceRur — цена в целых рублях (поле price_rur в ответе всегда целое
 * число без копеек), null — если поле в ответе отсутствует или не число.
 */
final class PlatiSearchItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $nameEng,
        public readonly string $url,
        public readonly int $cntSell,
        public readonly string $sellerName = '',
        public readonly ?int $priceRur = null,
    ) {
    }
}
