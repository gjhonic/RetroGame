<?php

namespace App\Service\User\Exceptions;

/** Пользователь пытается подписаться на самого себя. */
class CannotFollowSelfException extends \RuntimeException
{
}
