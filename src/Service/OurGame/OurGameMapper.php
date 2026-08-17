<?php

namespace App\Service\OurGame;

use App\Entity\Genre;
use App\Entity\OurGame;
use App\Entity\OurGameDownloadLink;

/** Маппинг сущности OurGame в массивы для JSON API. */
class OurGameMapper
{
    /** @return array<string, mixed> */
    public function toAdminListItem(OurGame $game): array
    {
        return [
            'id' => $game->getId(),
            'name' => $game->getName(),
            'slug' => $game->getSlug(),
            'status' => $game->getStatus()->value,
            'coverImageUrl' => $this->imageUrl($game->getCoverImagePath()),
            'currentVersion' => $game->getCurrentVersion(),
            'releaseDate' => $game->getReleaseDate()?->format('Y-m-d'),
            'genres' => $this->genreNames($game->getGenres()->toArray()),
        ];
    }

    /** @return array<string, mixed> */
    public function toDetail(OurGame $game): array
    {
        return [
            ...$this->toAdminListItem($game),
            'description' => $game->getDescription(),
            'bannerImageUrl' => $this->imageUrl($game->getBannerImagePath()),
            'screenshotUrls' => array_map(
                fn (string $path): string => (string) $this->imageUrl($path),
                $game->getScreenshotUrls() ?? [],
            ),
            'trailerUrl' => $game->getTrailerUrl(),
            'versionUpdatedAt' => $game->getVersionUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'genreIds' => array_map(static fn (Genre $genre): ?int => $genre->getId(), $game->getGenres()->toArray()),
            'downloadLinks' => array_map(
                $this->toDownloadLinkItem(...),
                $game->getDownloadLinks()->toArray(),
            ),
            'createdAt' => $game->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $game->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public function toDownloadLinkItem(OurGameDownloadLink $link): array
    {
        return [
            'id' => $link->getId(),
            'platform' => $link->getPlatform()->value,
            'url' => $link->getUrl(),
            'imageUrl' => $this->imageUrl($link->getImagePath()),
        ];
    }

    /**
     * @param array<int, Genre> $genres
     *
     * @return array<int, string>
     */
    private function genreNames(array $genres): array
    {
        return array_map(static fn (Genre $genre): string => $genre->getName(), $genres);
    }

    private function imageUrl(?string $path): ?string
    {
        return $path !== null ? '/' . $path : null;
    }
}
