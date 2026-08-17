<?php

namespace App\Dto\User;

use Symfony\Component\Validator\Constraints as Assert;

/** Тело запроса PATCH /api/cabinet/profile/nickname. */
class UpdateNicknameRequest
{
    #[Assert\NotBlank(message: 'Укажите имя пользователя.')]
    #[Assert\Length(
        min: 2,
        max: 50,
        minMessage: 'Имя пользователя должно быть не короче {{ limit }} символов.',
        maxMessage: 'Имя пользователя должно быть не длиннее {{ limit }} символов.',
    )]
    #[Assert\Regex(pattern: '/^[^<>]*$/u', message: 'Ник не должен содержать HTML-теги.')]
    public string $nickname = '';
}
