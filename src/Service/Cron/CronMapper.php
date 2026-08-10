<?php

namespace App\Service\Cron;

use App\Entity\Cron;
use App\Entity\CronRun;

/** Маппинг сущности Cron в массивы для JSON API. */
class CronMapper
{
    /** @return array<string, mixed> */
    public function toListItem(Cron $cron, ?CronRun $lastRun): array
    {
        return [
            'id' => $cron->getId(),
            'command' => $cron->getCommand(),
            'color' => $cron->getColor(),
            'lastRun' => $lastRun === null ? null : [
                'startedAt' => $lastRun->getStartedAt()->format(\DateTimeInterface::ATOM),
                'status' => $lastRun->getStatus()->value,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function toDetail(Cron $cron): array
    {
        return [
            'id' => $cron->getId(),
            'command' => $cron->getCommand(),
            'color' => $cron->getColor(),
            'createdAt' => $cron->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
