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
            'currency' => 'RUB',
            'isFree' => $price->isFree(),
            'isAvailableInRussia' => $price->isAvailableInRussia(),
            'store' => $steamAppId !== null ? self::STEAM_STORE_LABEL : null,
            'storeUrl' => $steamAppId !== null ? sprintf(self::STEAM_STORE_URL_TEMPLATE, $steamAppId) : null,
            'createdAt' => $price->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Сводка цены в Steam для карточки игры на публичной странице:
     * текущее состояние (по последнему снимку) + история для графика.
     * Отдельно от toApi() — той нужен полный снимок на конкретный день
     * (админка), этой — только то, что показывает карточка.
     *
     * @param array<int, GamePrice> $history история от старых к новым (см. GamePriceRepository::findHistoryForGame())
     *
     * @return array<string, mixed>
     */
    public function toPublicSummary(array $history, ?int $steamAppId): array
    {
        $latest = $history === [] ? null : $history[array_key_last($history)];

        return [
            'isFree' => $latest?->isFree() ?? false,
            'isAvailableInRussia' => $latest?->isAvailableInRussia() ?? true,
            'priceKopecks' => $latest?->getPriceKopecks(),
            'store' => $steamAppId !== null ? self::STEAM_STORE_LABEL : null,
            'storeUrl' => $steamAppId !== null ? sprintf(self::STEAM_STORE_URL_TEMPLATE, $steamAppId) : null,
            'history' => array_map(self::toHistoryPoint(...), $history),
        ];
    }

    /** @return array{date: string, priceKopecks: int|null} */
    private static function toHistoryPoint(GamePrice $price): array
    {
        return ['date' => $price->getDate()->format('Y-m-d'), 'priceKopecks' => $price->getPriceKopecks()];
    }
}
