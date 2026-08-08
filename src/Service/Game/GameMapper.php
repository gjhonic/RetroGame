<?php

namespace App\Service\Game;

use App\Entity\Game;
use App\Entity\Interfaces\NamedEntityInterface;

/** Маппинг сущности Game в массивы для JSON API. */
class GameMapper
{
    /** @return array<string, mixed> */
    public function toListItem(Game $game): array
    {
        return [
            'id' => $game->getId(),
            'name' => $game->getName(),
            'slug' => $game->getSlug(),
            'coverImageUrl' => $this->coverImageUrl($game),
            'description' => $game->getDescription(),
            'metacriticScore' => $game->getMetacriticScore(),
            'releaseYear' => $game->getReleaseDate()?->format('Y'),
        ];
    }

    /**
     * Пункт списка для таблицы в админке — в отличие от toListItem(), включает
     * разработчиков/издателей/жанры (репозиторий подгружает их через fetch-join,
     * см. GameRepository::findForAdminList(), поэтому лишних запросов не будет).
     *
     * @return array<string, mixed>
     */
    public function toAdminListItem(Game $game): array
    {
        return [
            ...$this->toListItem($game),
            'developers' => $this->names($game->getDevelopers()->toArray()),
            'publishers' => $this->names($game->getPublishers()->toArray()),
            'genres' => $this->names($game->getGenres()->toArray()),
        ];
    }

    /** @return array<string, mixed> */
    public function toDetail(Game $game): array
    {
        return [
            'id' => $game->getId(),
            'name' => $game->getName(),
            'slug' => $game->getSlug(),
            'coverImageUrl' => $this->coverImageUrl($game),
            'screenshotUrls' => $game->getScreenshotUrls() ?? [],
            'description' => $game->getDescription(),
            'rating' => $game->getRating(),
            'metacriticScore' => $game->getMetacriticScore(),
            'releaseDate' => $game->getReleaseDate()?->format('Y-m-d'),
            'developers' => $this->names($game->getDevelopers()->toArray()),
            'publishers' => $this->names($game->getPublishers()->toArray()),
            'genres' => $this->names($game->getGenres()->toArray()),
            'platforms' => $this->names($game->getPlatforms()->toArray()),
        ];
    }

    /**
     * @param array<int, NamedEntityInterface> $entities
     *
     * @return array<int, string>
     */
    private function names(array $entities): array
    {
        return array_map(static fn (NamedEntityInterface $entity) => $entity->getName(), $entities);
    }

    private function coverImageUrl(Game $game): ?string
    {
        return $game->getCoverImagePath() !== null ? '/' . $game->getCoverImagePath() : null;
    }
}
