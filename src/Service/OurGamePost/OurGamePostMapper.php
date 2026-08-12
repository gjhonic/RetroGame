<?php

namespace App\Service\OurGamePost;

use App\Entity\OurGame;
use App\Entity\OurGamePost;
use App\Entity\User;

/** Маппинг сущности OurGamePost в массивы для JSON API. */
class OurGamePostMapper
{
    /** @return array<string, mixed> */
    public function toAdminListItem(OurGamePost $post): array
    {
        return [
            'id' => $post->getId(),
            'game' => $this->game($post->getGame()),
            'author' => $this->author($post->getAuthor()),
            'type' => $post->getType()->value,
            'status' => $post->getStatus()->value,
            'postedAt' => $post->getPostedAt()->format('Y-m-d'),
            'imageUrl' => $this->imageUrl($post->getImagePath()),
            'title' => $post->getTitle(),
            'shortDescription' => $post->getShortDescription(),
        ];
    }

    /** @return array<string, mixed> */
    public function toDetail(OurGamePost $post): array
    {
        return [
            ...$this->toAdminListItem($post),
            'fullDescription' => $post->getFullDescription(),
            'createdAt' => $post->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $post->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private function game(OurGame $game): array
    {
        return [
            'id' => $game->getId(),
            'name' => $game->getName(),
        ];
    }

    /** @return array<string, mixed> */
    private function author(User $author): array
    {
        return [
            'id' => $author->getId(),
            'nickname' => $author->getNickname(),
            'email' => $author->getEmail(),
        ];
    }

    private function imageUrl(?string $path): ?string
    {
        return $path !== null ? '/' . $path : null;
    }
}
