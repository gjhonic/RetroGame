<?php

namespace App\Entity\Enum;

enum OurGameStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
