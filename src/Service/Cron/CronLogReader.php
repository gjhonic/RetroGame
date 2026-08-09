<?php

namespace App\Service\Cron;

use App\Entity\CronRun;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Читает файл лога запуска крона, записанный CronTrackingListener. */
class CronLogReader
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/log/cron')]
        private readonly string $logDir,
    ) {
    }

    /** Возвращает содержимое лог-файла или null, если лога нет/файл не найден. */
    public function read(CronRun $cronRun): ?string
    {
        $logPath = $cronRun->getLogPath();
        if ($logPath === null) {
            return null;
        }

        $absolutePath = $this->logDir . '/' . $logPath;
        if (!is_file($absolutePath)) {
            return null;
        }

        $content = file_get_contents($absolutePath);

        return $content === false ? null : $content;
    }
}
