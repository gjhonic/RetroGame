<?php

namespace App\Dto\User;

/** Тело запроса PATCH /api/cabinet/profile/privacy. */
class UpdatePrivacyRequest
{
    public bool $isProfilePublic = false;
}
