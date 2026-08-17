<?php

namespace App\Entity\Enum;

/** Тип реакции пользователя на игру — лайк или дизлайк. */
enum GameReactionType: string
{
    case Like = 'like';
    case Dislike = 'dislike';
}
