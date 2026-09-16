<?php

namespace App\Service\GgselGame;

use App\Entity\Game;
use App\Entity\GgselGame;

/** Маппинг сущности GgselGame в массивы для JSON API. */
class GgselGameMapper
{
    /** @return array<string, mixed> */
    public function toAdminListItem(GgselGame $ggselGame): array
    {
        $game = $ggselGame->getGame();

        return [
            'id' => $ggselGame->getId(),
            'gameId' => $game->getId(),
            'gameName' => $game->getName(),
            'gameCoverImageUrl' => $this->coverImageUrl($game),
            'url' => $ggselGame->getUrl(),
            'createdAt' => $ggselGame->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $ggselGame->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    /** @return array<string, mixed> */
    public function toDetail(GgselGame $ggselGame): array
    {
        $game = $ggselGame->getGame();

        return [
            'id' => $ggselGame->getId(),
            'gameId' => $game->getId(),
            'gameName' => $game->getName(),
            'gameSlug' => $game->getSlug(),
            'gameCoverImageUrl' => $this->coverImageUrl($game),
            'url' => $ggselGame->getUrl(),
            'createdAt' => $ggselGame->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $ggselGame->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    private function coverImageUrl(Game $game): ?string
    {
        return $game->getCoverImagePath() !== null ? '/' . $game->getCoverImagePath() : null;
    }
}
