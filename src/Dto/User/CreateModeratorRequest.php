<?php

namespace App\Dto\User;

use Symfony\Component\Validator\Constraints as Assert;

/** Тело запроса POST /api/admin/users/moderators. */
class CreateModeratorRequest
{
    #[Assert\NotBlank(message: 'Укажите email.')]
    #[Assert\Email(message: 'Некорректный email.')]
    public string $email = '';

    #[Assert\NotBlank(message: 'Укажите пароль.')]
    #[Assert\Length(
        min: 8,
        max: 255,
        minMessage: 'Пароль должен быть не короче {{ limit }} символов.',
    )]
    public string $password = '';

    #[Assert\NotBlank(message: 'Укажите имя пользователя.')]
    #[Assert\Length(
        min: 2,
        max: 50,
        minMessage: 'Имя пользователя должно быть не короче {{ limit }} символов.',
        maxMessage: 'Имя пользователя должно быть не длиннее {{ limit }} символов.',
    )]
    public string $nickname = '';
}
