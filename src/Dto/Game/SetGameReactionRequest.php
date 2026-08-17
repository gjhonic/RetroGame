<?php

namespace App\Dto\Game;

use Symfony\Component\Validator\Constraints as Assert;

/** Тело запроса PUT /api/cabinet/games/{slug}/reaction. */
class SetGameReactionRequest
{
    #[Assert\NotBlank(message: 'Укажите тип реакции.')]
    #[Assert\Choice(choices: ['like', 'dislike'], message: 'Тип реакции должен быть like или dislike.')]
    public string $type = '';
}
