<?php

namespace App\Dto\OurGame;

use Symfony\Component\Validator\Constraints as Assert;

/** Тело запроса POST/PATCH .../download-links[/{linkId}]. */
class OurGameDownloadLinkRequest
{
    #[Assert\NotBlank(message: 'Укажите платформу.')]
    #[Assert\Choice(choices: ['windows', 'macos', 'linux', 'android', 'web'], message: 'Недопустимая платформа.')]
    public string $platform = '';

    #[Assert\NotBlank(message: 'Укажите ссылку.')]
    #[Assert\Url(message: 'Некорректная ссылка.')]
    #[Assert\Length(max: 500, maxMessage: 'Ссылка должна быть не длиннее {{ limit }} символов.')]
    public string $url = '';
}
