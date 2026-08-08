<?php

namespace App\Entity\Enum;

enum SteamGameStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
}
