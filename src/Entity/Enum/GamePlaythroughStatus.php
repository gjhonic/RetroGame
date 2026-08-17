<?php

namespace App\Entity\Enum;

/** Статус прохождения игры пользователем. */
enum GamePlaythroughStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Dropped = 'dropped';
}
