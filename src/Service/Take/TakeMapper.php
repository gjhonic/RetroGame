<?php

namespace App\Service\Take;

use App\Entity\Game;
use App\Entity\Take;
use App\Entity\TakeComment;
use App\Entity\User;

/** Маппинг сущностей Take/TakeComment в массивы для JSON API. */
class TakeMapper
{
    /** @return array<string, mixed> */
    public function toListItem(Take $take, int $likeCount, int $dislikeCount, int $commentCount): array
    {
        return [
            'id' => $take->getId(),
            'text' => $take->getText(),
            'createdAt' => $take->getCreatedAt()->format('Y-m-d\TH:i:sP'),
            'author' => $this->author($take->getAuthor()),
            'game' => $this->game($take->getGame()),
            'likeCount' => $likeCount,
            'dislikeCount' => $dislikeCount,
            'commentCount' => $commentCount,
        ];
    }

    /**
     * @param array<int, TakeComment> $comments
     *
     * @return array<string, mixed>
     */
    public function toDetail(Take $take, int $likeCount, int $dislikeCount, array $comments): array
    {
        return [
            ...$this->toListItem($take, $likeCount, $dislikeCount, \count($comments)),
            'comments' => array_map($this->toComment(...), $comments),
        ];
    }

    /** @return array<string, mixed> */
    public function toComment(TakeComment $comment): array
    {
        return [
            'id' => $comment->getId(),
            'text' => $comment->getText(),
            'createdAt' => $comment->getCreatedAt()->format('Y-m-d\TH:i:sP'),
            'author' => $this->author($comment->getAuthor()),
        ];
    }

    /** @return array<string, mixed> */
    private function author(User $user): array
    {
        return [
            'id' => $user->getId(),
            'nickname' => $user->getNickname(),
        ];
    }

    /** @return array<string, mixed> */
    private function game(Game $game): array
    {
        return [
            'id' => $game->getId(),
            'name' => $game->getName(),
            'slug' => $game->getSlug(),
        ];
    }
}
