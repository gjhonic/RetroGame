<?php

namespace App\Dto\User;

use Symfony\Component\Validator\Constraints as Assert;

/** Тело запроса PATCH /api/cabinet/profile/password. */
class ChangePasswordRequest
{
    #[Assert\NotBlank(message: 'Укажите текущий пароль.')]
    public string $currentPassword = '';

    #[Assert\NotBlank(message: 'Укажите новый пароль.')]
    #[Assert\Length(
        min: 8,
        max: 255,
        minMessage: 'Пароль должен быть не короче {{ limit }} символов.',
    )]
    public string $newPassword = '';
}
