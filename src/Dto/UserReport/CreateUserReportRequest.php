<?php

namespace App\Dto\UserReport;

use Symfony\Component\Validator\Constraints as Assert;

/** Тело запроса POST /api/user-reports. */
class CreateUserReportRequest
{
    #[Assert\NotNull(message: 'Укажите раздел отчёта.')]
    #[Assert\Choice(choices: [1, 2, 3], message: 'Некорректный раздел отчёта.')]
    public ?int $type = null;

    #[Assert\NotBlank(message: 'Опишите проблему в комментарии.')]
    #[Assert\Length(max: 4000, maxMessage: 'Комментарий не должен превышать {{ limit }} символов.')]
    public string $comment = '';
}
