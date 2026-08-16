<?php

namespace App\Dto\ScoreDieAgain;

use Symfony\Component\Validator\Constraints as Assert;

/** Тело запроса POST /api/score-die-again. */
class CreateScoreDieAgainRequest
{
    #[Assert\NotBlank(message: 'Укажите ник игрока.')]
    #[Assert\Length(max: 50, maxMessage: 'Ник не должен превышать {{ limit }} символов.')]
    #[Assert\Regex(pattern: '/^[^<>]*$/u', message: 'Ник не должен содержать HTML-теги.')]
    public string $nickname = '';

    #[Assert\PositiveOrZero(message: 'Некорректный уровень.')]
    public int $level = 0;

    #[Assert\PositiveOrZero(message: 'Некорректное время выживания.')]
    public int $survivedSeconds = 0;

    #[Assert\PositiveOrZero(message: 'Некорректное количество убийств.')]
    public int $kills = 0;
}
