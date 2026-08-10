<?php

namespace App\Dto\Take;

use Symfony\Component\Validator\Constraints as Assert;

/** Тело запроса PUT /api/cabinet/takes/{id}/reaction. */
class SetTakeReactionRequest
{
    #[Assert\NotBlank(message: 'Укажите тип реакции.')]
    #[Assert\Choice(choices: ['like', 'dislike'], message: 'Тип реакции должен быть like или dislike.')]
    public string $type = '';
}
