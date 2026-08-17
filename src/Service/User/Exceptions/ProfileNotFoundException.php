<?php

namespace App\Service\User\Exceptions;

/** Пользователь с таким ником не найден, либо его профиль закрыт для просмотра. */
class ProfileNotFoundException extends \RuntimeException
{
}
