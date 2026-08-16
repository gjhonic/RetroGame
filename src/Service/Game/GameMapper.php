<?php

namespace App\Service\Game;

use App\Entity\Game;
use App\Entity\Interfaces\NamedEntityInterface;

/** Маппинг сущности Game в массивы для JSON API. */
class GameMapper
{
    /**
     * Жанры, которые полностью скрываются из публичной части сайта (каталог,
     * фильтры, страница игры) — вне зависимости от того, что ввёл пользователь
     * в фильтр. В админке такие игры по-прежнему видны.
     */
    public const array HIDDEN_PUBLIC_GENRE_NAMES = ['Сексуальный контент'];

    /** Скрыта ли игра от публичной части сайта по жанру (см. HIDDEN_PUBLIC_GENRE_NAMES). */
    public function isHiddenFromPublic(Game $game): bool
    {
        foreach ($game->getGenres() as $genre) {
            if (\in_array($genre->getName(), self::HIDDEN_PUBLIC_GENRE_NAMES, true)) {
                return true;
            }
        }

        return false;
    }

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
            'popularity' => $game->getPopularity(),
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
    public function toDetail(
        Game $game,
        int $likeCount = 0,
        int $dislikeCount = 0,
        ?string $myReaction = null,
        bool $myFavorite = false,
        ?string $myStatus = null,
    ): array {
        return [
            'id' => $game->getId(),
            'name' => $game->getName(),
            'slug' => $game->getSlug(),
            'coverImageUrl' => $this->coverImageUrl($game),
            'screenshotUrls' => $game->getScreenshotUrls() ?? [],
            'description' => $game->getDescription(),
            'rating' => $game->getRating(),
            'metacriticScore' => $game->getMetacriticScore(),
            'popularity' => $game->getPopularity(),
            'releaseDate' => $game->getReleaseDate()?->format('Y-m-d'),
            'developers' => $this->names($game->getDevelopers()->toArray()),
            'publishers' => $this->names($game->getPublishers()->toArray()),
            'genres' => $this->names($game->getGenres()->toArray()),
            'platforms' => $this->names($game->getPlatforms()->toArray()),
            'likeCount' => $likeCount,
            'dislikeCount' => $dislikeCount,
            'myReaction' => $myReaction,
            'myFavorite' => $myFavorite,
            'myStatus' => $myStatus,
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
