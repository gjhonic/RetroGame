<?php

namespace App\Dto\Take;

use Symfony\Component\Validator\Constraints as Assert;

/** Тело запроса POST /api/cabinet/takes/{id}/comments. */
class CreateTakeCommentRequest
{
    #[Assert\NotBlank(message: 'Комментарий не может быть пустым.')]
    #[Assert\Length(max: 1000, maxMessage: 'Комментарий не должен превышать {{ limit }} символов.')]
    #[Assert\Regex(pattern: '/^[^<>]*$/u', message: 'Комментарий не должен содержать HTML-теги.')]
    public string $text = '';
}
