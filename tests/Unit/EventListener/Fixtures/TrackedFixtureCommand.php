<?php

namespace App\Tests\Unit\EventListener\Fixtures;

use App\Cron\Attribute\AsTrackedCron;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'test:tracked')]
#[AsTrackedCron(staleAfterMinutes: 15)]
class TrackedFixtureCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return Command::SUCCESS;
    }
}
