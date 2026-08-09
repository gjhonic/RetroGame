<?php

namespace App\Service\Cron;

use App\Entity\CronRun;

/** Маппинг сущности CronRun в массивы для JSON API. */
class CronRunMapper
{
    /** @return array<string, mixed> */
    public function toListItem(CronRun $cronRun): array
    {
        return [
            'id' => $cronRun->getId(),
            'command' => $cronRun->getCommand(),
            'status' => $cronRun->getStatus()->value,
            'startedAt' => $cronRun->getStartedAt()->format(\DateTimeInterface::ATOM),
            'finishedAt' => $cronRun->getFinishedAt()?->format(\DateTimeInterface::ATOM),
            'durationMs' => $cronRun->getDurationMs(),
            'memoryPeakBytes' => $cronRun->getMemoryPeakBytes(),
            'exitCode' => $cronRun->getExitCode(),
        ];
    }

    /** @return array<string, mixed> */
    public function toDetail(CronRun $cronRun): array
    {
        return [
            ...$this->toListItem($cronRun),
            'arguments' => $cronRun->getArguments(),
            'errorMessage' => $cronRun->getErrorMessage(),
            'hasLog' => $cronRun->getLogPath() !== null,
        ];
    }
}
