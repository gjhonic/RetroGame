<?php

namespace App\Dto\Take;

use Symfony\Component\Validator\Constraints as Assert;

/** Тело запроса POST /api/cabinet/takes. */
class CreateTakeRequest
{
    #[Assert\NotBlank(message: 'Укажите игру.')]
    #[Assert\Positive(message: 'Некорректный ID игры.')]
    public int $gameId = 0;

    #[Assert\NotBlank(message: 'Текст тэйка не может быть пустым.')]
    #[Assert\Length(max: 1000, maxMessage: 'Текст тэйка не должен превышать {{ limit }} символов.')]
    #[Assert\Regex(pattern: '/^[^<>]*$/u', message: 'Текст не должен содержать HTML-теги.')]
    public string $text = '';
}
