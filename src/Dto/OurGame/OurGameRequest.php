<?php

namespace App\Dto\OurGame;

use Symfony\Component\Validator\Constraints as Assert;

/** Тело запроса POST/PATCH /api/admin/our-games[/{id}] — форма всегда шлёт полный набор полей. */
class OurGameRequest
{
    #[Assert\NotBlank(message: 'Укажите название.')]
    #[Assert\Length(max: 255, maxMessage: 'Название должно быть не длиннее {{ limit }} символов.')]
    public string $name = '';

    public ?string $description = null;

    #[Assert\NotBlank(message: 'Укажите статус.')]
    #[Assert\Choice(choices: ['draft', 'published'], message: 'Недопустимый статус.')]
    public string $status = 'draft';

    #[Assert\Length(max: 50, maxMessage: 'Версия должна быть не длиннее {{ limit }} символов.')]
    public ?string $currentVersion = null;

    /** Дата в формате YYYY-MM-DD. */
    #[Assert\Date(message: 'Некорректная дата выхода.')]
    public ?string $releaseDate = null;

    #[Assert\Url(message: 'Некорректная ссылка на трейлер.')]
    public ?string $trailerUrl = null;

    /** @var int[] */
    public array $genreIds = [];
}
