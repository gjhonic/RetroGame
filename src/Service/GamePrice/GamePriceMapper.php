<?php

namespace App\Service\GamePrice;

use App\Entity\GamePrice;

/**
 * Маппинг сущности GamePrice в массив для JSON API. store/storeUrl не
 * хранятся в GamePrice — источник цены сейчас всегда Steam, поэтому
 * магазин/ссылка выводятся на лету из appid (см. вызовы toApi() в
 * Api/Admin и Api/Public GameApiController).
 */
class GamePriceMapper
{
    private const string STEAM_STORE_LABEL = 'Steam';
    private const string STEAM_STORE_URL_TEMPLATE = 'https://store.steampowered.com/app/%d/';

    /** @return array<string, mixed> */
    public function toApi(GamePrice $price, ?int $steamAppId = null): array
    {
        return [
            'id' => $price->getId(),
            'gameId' => $price->getGame()->getId(),
            'date' => $price->getDate()->format('Y-m-d'),
            'priceKopecks' => $price->getPriceKopecks(),
            'currency' => $price->getCurrency(),
            'isFree' => $price->isFree(),
            'isAvailableInRussia' => $price->isAvailableInRussia(),
            'store' => $steamAppId !== null ? self::STEAM_STORE_LABEL : null,
            'storeUrl' => $steamAppId !== null ? sprintf(self::STEAM_STORE_URL_TEMPLATE, $steamAppId) : null,
            'createdAt' => $price->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
