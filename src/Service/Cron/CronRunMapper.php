<?php

namespace App\Service\Cron;

use App\Entity\Cron;
use App\Entity\CronRun;

/** Маппинг сущности CronRun в массивы для JSON API. */
class CronRunMapper
{
    /**
     * @param Cron|null $cron крон, к которому относится запуск (по совпадению command —
     *                        связи в БД нет, см. App\Entity\CronRun), для отображения
     *                        настроенных пользователем названия/цвета в истории запусков
     *
     * @return array<string, mixed>
     */
    public function toListItem(CronRun $cronRun, ?Cron $cron = null): array
    {
        return [
            'id' => $cronRun->getId(),
            'command' => $cronRun->getCommand(),
            'cronName' => $cron?->getName(),
            'cronColor' => $cron?->getColor(),
            'status' => $cronRun->getStatus()->value,
            'startedAt' => $cronRun->getStartedAt()->format(\DateTimeInterface::ATOM),
            'finishedAt' => $cronRun->getFinishedAt()?->format(\DateTimeInterface::ATOM),
            'durationMs' => $cronRun->getDurationMs(),
            'memoryPeakBytes' => $cronRun->getMemoryPeakBytes(),
            'exitCode' => $cronRun->getExitCode(),
        ];
    }

    /** @return array<string, mixed> */
    public function toDetail(CronRun $cronRun, ?Cron $cron = null): array
    {
        return [
            ...$this->toListItem($cronRun, $cron),
            'arguments' => $cronRun->getArguments(),
            'errorMessage' => $cronRun->getErrorMessage(),
            'hasLog' => $cronRun->getLogPath() !== null,
        ];
    }
}
