<?php

namespace AuthToken\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use AuthToken\database\Migrations;

class MigrationCommand extends Command
{
    protected static string $defaultName = 'migrate';

    protected function configure(): void
    {
        $this->setName('migrate')->setDescription('Initialize the application by migrating the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $migration = new Migrations();
        echo $migration->makeMigrations();
        return Command::SUCCESS;
    }
}