<?php

namespace App\Dto\Game;

use Symfony\Component\Validator\Constraints as Assert;

/** Тело запроса PUT /api/cabinet/games/{slug}/status. */
class SetGameStatusRequest
{
    #[Assert\NotBlank(message: 'Укажите статус прохождения.')]
    #[Assert\Choice(
        choices: ['planned', 'in_progress', 'completed', 'dropped'],
        message: 'Некорректный статус прохождения.',
    )]
    public string $status = '';
}
