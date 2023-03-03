<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:h2g',
    description: 'Convert a tcx file generated with the huawei gt2 watch to a valid garmin tcx file.',
)]
class H2gCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Filename')
            ->addOption('avg-bpm', null, InputOption::VALUE_REQUIRED, 'Average Heart Rate Bpm')
            ->addOption('max-bpm', null, InputOption::VALUE_REQUIRED, 'Max Heart Rate Bpm')
            ->addOption('cadence', null, InputOption::VALUE_REQUIRED, 'Cadence')
            ->addOption('calories', null, InputOption::VALUE_REQUIRED, 'Calories')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $input->getArgument('file');

        if ($file) {
            $io->note(sprintf('You passed an argument: %s', $file));
        }

        //TODO read the file. Internally it is a xml file.

        //TODO read the template file

        //TODO replace the values in the template file

        //TODO complete it with the values from the option

        if ($input->getOption('avg-bpm')) {
            // ...
        }

        //TODO save the external file

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return Command::SUCCESS;
    }
}
