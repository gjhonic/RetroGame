<?php

namespace App\Cron;

use App\Cron\Attribute\AsTrackedCron;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** Находит команды, помеченные #[AsTrackedCron] — источник правды для справочника кронов (App\Entity\Cron). */
class CronDiscoveryService
{
    /**
     * @param iterable<Command> $commands
     */
    public function __construct(
        #[AutowireIterator('console.command')]
        private readonly iterable $commands,
    ) {
    }

    /** @return list<string> имена команд (Command::getName()), помеченных #[AsTrackedCron] */
    public function discoverTrackedCommandNames(): array
    {
        $names = [];

        foreach ($this->commands as $command) {
            if ((new \ReflectionClass($command))->getAttributes(AsTrackedCron::class) === []) {
                continue;
            }

            $name = $command->getName();
            if ($name !== null) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
