<?php

namespace AuthToken\Commands;

use Random\RandomException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use AuthToken\Secret\Secret;

class SecretCommand extends Command
{
    protected static string $defaultName = 'secret';

    protected function configure(): void
    {
        $this->setName('secret')->setDescription('Initialize the application by generating secrets');
    }

    /**
     * @throws RandomException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $secret = new Secret();
        $output->writeln($secret->generateSecret());

        return Command::SUCCESS;
    }
}