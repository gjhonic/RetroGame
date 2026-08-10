<?php

namespace App\Service\User\Exceptions;

/** Текущий пароль не совпадает с переданным при смене пароля. */
class InvalidCurrentPasswordException extends \RuntimeException
{
}
