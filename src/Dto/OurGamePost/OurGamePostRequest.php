<?php

namespace App\Dto\OurGamePost;

use Symfony\Component\Validator\Constraints as Assert;

/** Тело запроса POST/PATCH /api/admin/our-game-posts[/{id}] — форма всегда шлёт полный набор полей. */
class OurGamePostRequest
{
    #[Assert\NotBlank(message: 'Укажите игру.')]
    public ?int $gameId = null;

    #[Assert\NotBlank(message: 'Укажите тип поста.')]
    #[Assert\Choice(choices: ['info', 'minor_update', 'major_update'], message: 'Недопустимый тип поста.')]
    public string $type = 'info';

    #[Assert\NotBlank(message: 'Укажите статус.')]
    #[Assert\Choice(choices: ['draft', 'published'], message: 'Недопустимый статус.')]
    public string $status = 'draft';

    /** Дата в формате YYYY-MM-DD. */
    #[Assert\NotBlank(message: 'Укажите дату поста.')]
    #[Assert\Date(message: 'Некорректная дата поста.')]
    public string $postedAt = '';

    #[Assert\NotBlank(message: 'Укажите заголовок.')]
    #[Assert\Length(max: 255, maxMessage: 'Заголовок должен быть не длиннее {{ limit }} символов.')]
    public string $title = '';

    /** HTML из редактора в админке — без ограничения длины. */
    #[Assert\NotBlank(message: 'Укажите краткое описание.')]
    public string $shortDescription = '';

    /** HTML из редактора в админке. */
    public ?string $fullDescription = null;
}
