<?php

namespace App\Service\SteamGame;

use App\Entity\Game;
use App\Entity\SteamGame;

/** Маппинг сущности SteamGame в массивы для JSON API. */
class SteamGameMapper
{
    /** @return array<string, mixed> */
    public function toAdminListItem(SteamGame $steamGame): array
    {
        $game = $steamGame->getGame();

        return [
            'id' => $steamGame->getId(),
            'steamAppId' => $steamGame->getSteamAppId(),
            'status' => $steamGame->getStatus()->value,
            'gameId' => $game?->getId(),
            'gameName' => $game?->getName(),
            'gameCoverImageUrl' => $this->coverImageUrl($game),
            'attempts' => $steamGame->getAttempts(),
            'fetchedAt' => $steamGame->getFetchedAt()?->format('Y-m-d H:i:s'),
            'lastAttemptAt' => $steamGame->getLastAttemptAt()?->format('Y-m-d H:i:s'),
        ];
    }

    /** @return array<string, mixed> */
    public function toDetail(SteamGame $steamGame): array
    {
        $game = $steamGame->getGame();

        return [
            'id' => $steamGame->getId(),
            'steamAppId' => $steamGame->getSteamAppId(),
            'status' => $steamGame->getStatus()->value,
            'gameId' => $game?->getId(),
            'gameName' => $game?->getName(),
            'gameSlug' => $game?->getSlug(),
            'gameCoverImageUrl' => $this->coverImageUrl($game),
            'lastError' => $steamGame->getLastError(),
            'attempts' => $steamGame->getAttempts(),
            'fetchedAt' => $steamGame->getFetchedAt()?->format('Y-m-d H:i:s'),
            'lastAttemptAt' => $steamGame->getLastAttemptAt()?->format('Y-m-d H:i:s'),
            'rawData' => $steamGame->getRawData(),
        ];
    }

    private function coverImageUrl(?Game $game): ?string
    {
        return $game?->getCoverImagePath() !== null ? '/' . $game->getCoverImagePath() : null;
    }
}
