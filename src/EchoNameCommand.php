<?php

namespace App\Cli;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class EchoNameCommand extends Command
{
    protected static $defaultName = 'echo:name';

    protected function configure(): void
    {
        $this->setDescription("Echos back the name provided at the prompt.");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');

        $question = new Question('What\'s your name? ', 'World');

        $nameAnswer = $helper->ask($input, $output, $question);

        $output->writeln("Hello, $nameAnswer!");

        return Command::SUCCESS;
    }
}
