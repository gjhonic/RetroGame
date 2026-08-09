<?php

namespace App\Cron\Attribute;

/**
 * Помечает консольную команду как крон, за которым нужно вести учёт —
 * каждый запуск фиксируется в БД (App\Entity\CronRun) и логируется в файл,
 * см. App\EventListener\CronTrackingListener.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsTrackedCron
{
    /**
     * @param int $staleAfterMinutes Если запуск дольше этого времени остаётся
     *                                в статусе "running" (процесс, вероятно, убит) —
     *                                при следующем запуске любого трекаемого крона
     *                                он будет автоматически помечен как failed.
     */
    public function __construct(public readonly int $staleAfterMinutes = 30)
    {
    }
}
