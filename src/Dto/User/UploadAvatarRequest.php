<?php

namespace App\Dto\User;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

/** Тело запроса POST /api/cabinet/profile/avatar (multipart/form-data, поле "avatar"). */
class UploadAvatarRequest
{
    #[Assert\NotNull(message: 'Выберите файл.')]
    #[Assert\Image(
        maxSize: '2M',
        mimeTypes: ['image/jpeg', 'image/png'],
        mimeTypesMessage: 'Допустимые форматы: JPG, PNG.',
        maxSizeMessage: 'Файл слишком большой (максимум {{ limit }}).',
        maxWidth: 400,
        maxHeight: 400,
        maxWidthMessage: 'Ширина изображения не должна превышать {{ max_width }}px.',
        maxHeightMessage: 'Высота изображения не должна превышать {{ max_height }}px.',
    )]
    public ?UploadedFile $file = null;
}
