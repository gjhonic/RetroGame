<?php

namespace App\Entity\Enum;

/** Раздел, к которому относится пользовательский отчёт (баг/жалоба). */
enum UserReportType: int
{
    case Site = 1;
    case MobileApp = 2;
    case DieAgain = 3;

    /** Человекочитаемое название раздела — для админки. */
    public function label(): string
    {
        return match ($this) {
            self::Site => 'Сайт',
            self::MobileApp => 'Мобильное приложение',
            self::DieAgain => 'Игра DIE//AGAIN',
        };
    }
}
