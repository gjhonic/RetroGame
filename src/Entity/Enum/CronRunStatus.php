<?php

namespace App\Entity\Enum;

enum CronRunStatus: string
{
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';
}
