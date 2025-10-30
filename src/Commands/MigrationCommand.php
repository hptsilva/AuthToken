<?php

namespace AuthToken\Commands;

use AuthToken\Exception\ErrorConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use AuthToken\Database\Migrations;
use Dotenv\Dotenv;

class MigrationCommand extends Command
{
    protected static string $defaultName = 'migrate';

    protected function configure(): void
    {
        $this->setName('migrate')->setDescription('Initialize the application by migrating the database');
    }

    /**
     * @throws ErrorConnection
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dotenv = Dotenv::createImmutable(getcwd());
        $dotenv->load();

        $migration = new Migrations();
        $response = $migration->makeMigrations($output);
        if (is_string($response)) {
            echo $response;
        } else {
            $response->render();
        }

        return Command::SUCCESS;
    }
}