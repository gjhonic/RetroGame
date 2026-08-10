<?php

namespace App\Entity\Enum;

/** Тип реакции пользователя на тэйк — лайк или дизлайк. */
enum TakeReactionType: string
{
    case Like = 'like';
    case Dislike = 'dislike';
}
