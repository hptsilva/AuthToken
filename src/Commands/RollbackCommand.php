<?php

namespace AuthToken\Commands;

use AuthToken\Database\Rollback;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RollbackCommand extends Command
{
    protected static string $defaultName = 'rollback';

    protected function configure(): void
    {
        $this->setName('rollback')->setDescription('Rollback all available tables');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rollback = new Rollback();
        echo $rollback->makeRollback();
        return Command::SUCCESS;
    }
}