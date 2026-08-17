<?php

namespace App\Service\OurGamePost\Exceptions;

/** Игра с переданным gameId не найдена при создании/обновлении поста. */
class OurGameNotFoundException extends \RuntimeException
{
}
