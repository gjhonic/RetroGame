<?php

namespace App\Service\PlatiGame;

use App\Entity\Game;
use App\Entity\PlatiGame;
use App\Entity\PlatiGamePrice;

/** Маппинг сущности PlatiGame в массивы для JSON API. */
class PlatiGameMapper
{
    /** @return array<string, mixed> */
    public function toAdminListItem(PlatiGame $platiGame): array
    {
        $game = $platiGame->getGame();

        return [
            'id' => $platiGame->getId(),
            'gameId' => $game->getId(),
            'gameName' => $game->getName(),
            'gameCoverImageUrl' => $this->coverImageUrl($game),
            'url' => $platiGame->getUrl(),
            'sellerName' => $platiGame->getSellerName(),
            'createdAt' => $platiGame->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $platiGame->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    /** @return array<string, mixed> */
    public function toDetail(PlatiGame $platiGame): array
    {
        $game = $platiGame->getGame();

        return [
            'id' => $platiGame->getId(),
            'gameId' => $game->getId(),
            'gameName' => $game->getName(),
            'gameSlug' => $game->getSlug(),
            'gameCoverImageUrl' => $this->coverImageUrl($game),
            'url' => $platiGame->getUrl(),
            'sellerName' => $platiGame->getSellerName(),
            'createdAt' => $platiGame->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $platiGame->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Сводка цены одного продавца для карточки игры на публичной странице:
     * текущая цена (по последнему снимку) + история для графика.
     *
     * @param array<int, PlatiGamePrice> $history история от старых к новым
     *                                             (см. PlatiGamePriceRepository::findHistoryForPlatiGame())
     *
     * @return array<string, mixed>
     */
    public function toPublicPriceSummary(PlatiGame $platiGame, array $history): array
    {
        $latest = $history === [] ? null : $history[array_key_last($history)];

        return [
            'sellerName' => $platiGame->getSellerName(),
            'url' => $platiGame->getUrl(),
            'priceKopecks' => $latest?->getPriceKopecks(),
            'history' => array_map(self::toHistoryPoint(...), $history),
        ];
    }

    /** @return array{date: string, priceKopecks: int|null} */
    private static function toHistoryPoint(PlatiGamePrice $price): array
    {
        return ['date' => $price->getDate()->format('Y-m-d'), 'priceKopecks' => $price->getPriceKopecks()];
    }

    private function coverImageUrl(Game $game): ?string
    {
        return $game->getCoverImagePath() !== null ? '/' . $game->getCoverImagePath() : null;
    }
}
