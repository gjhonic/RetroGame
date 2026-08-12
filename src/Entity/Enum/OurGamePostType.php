<?php

namespace App\Entity\Enum;

enum OurGamePostType: string
{
    case Info = 'info';
    case MinorUpdate = 'minor_update';
    case MajorUpdate = 'major_update';
}
