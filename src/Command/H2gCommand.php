<?php

namespace App\Command;

use DOMElement;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:h2g',
    description: 'Convert a tcx file generated with the huawei gt2 watch to a valid garmin tcx file.',
)]
class H2gCommand extends Command
{
    public function __construct(
        private readonly string $uploadDirectory,
        private readonly string $resultDirectory,
        private readonly string $garminXsdFile,
        string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Filename')
            ->addArgument('calories', InputArgument::REQUIRED, 'Total number of calories (integer)')
            ->addOption('avg-bpm', null, InputOption::VALUE_REQUIRED, 'Average Heart Rate Bpm')
            ->addOption('max-bpm', null, InputOption::VALUE_REQUIRED, 'Max Heart Rate Bpm')
            ->addOption('cadence', null, InputOption::VALUE_REQUIRED, 'Cadence')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filename = basename($input->getArgument('file'));
        $nCalories = (int) $input->getArgument('calories');

        if ($filename) {
            $io->note(sprintf('You passed an argument: %s', $filename));
        }

        //read the file $filename.
        $xml = new \DOMDocument();
        $read = $xml->load($this->uploadDirectory . DIRECTORY_SEPARATOR . $filename);

        if (false === $read) {
            $io->error('Failed loading XML: ' . $filename);
            return Command::FAILURE;
        }

        //delete the attributes xsi:schemaLocation, creator and version in the TrainingCenterDatabase element.
        $tcx = $xml->getElementsByTagName('TrainingCenterDatabase')->item(0);
        $tcx->removeAttribute('creator');
        $tcx->removeAttribute('version');
//        $tcx->removeAttribute('xsi:schemaLocation');
        $io->info('Attributes xsi:schemaLocation, creator and version removed from TrainingCenterDatabase element');

        //replace the content of the Sport attribute in the Activity element with the value "Running".
        $activity = $xml->getElementsByTagName('Activity')->item(0);
        $activity->setAttribute('Sport', 'Running');
        $io->info('Content of attribute Sport in Activity element replaced with the value "Running"');

        //delete the CumulativeClimb element in each Lap element.
        $laps = $xml->getElementsByTagName('Lap');
        $caloriesPerLap = (integer) ($nCalories / $laps->length);
        foreach ($laps as $lap) {
            /** @var DOMElement $lap */
            $cumulativeBlind = $lap->getElementsByTagName('CumulativeClimb')->item(0);
            if ($cumulativeBlind) {
                $lap->removeChild($cumulativeBlind);
                $io->info('CumulativeClimb Element removed from the Lap element');
            }
            $cumulativeDecrease = $lap->getElementsByTagName('CumulativeDecrease')->item(0);
            if ($cumulativeDecrease) {
                $lap->removeChild($cumulativeDecrease);
                $io->info('CumulativeDecrease Element removed from the Lap element');
            }

            $track = $lap->getElementsByTagName('Track')->item(0);
            if(!$track) {
                $io->error('No Track element found in the Lap element');
                return Command::FAILURE;
            }

            //add the TriggerMethod element to the Lap element with the value "Manual".
            $triggerMethod = $lap->getElementsByTagName('TriggerMethod')->item(0);
            if (!$triggerMethod) {
                $triggerMethod = $xml->createElement('TriggerMethod', 'Manual');
                $lap->insertBefore($triggerMethod, $track);
                $io->info('TriggerMethod Element added to the Lap element before the Track Element');
            }
            $lastElementInserted = $triggerMethod;

            if ($input->hasOption('cadence') && $input->getOption('cadence')) {
                $cadence = $input->getOption('cadence');
                $cadenceElement = $xml->createElement('Cadence', $cadence);
                $lap->insertBefore($cadenceElement, $triggerMethod);
                $io->info('Optional Cadence Element added to the Lap element before the TriggerMethod Element');
                $lastElementInserted = $cadenceElement;
            }

            //add the Intensity element to the Lap element with the value "Active".
            $intensity = $lap->getElementsByTagName('Intensity')->item(0);
            if (!$intensity) {
                $intensity = $xml->createElement('Intensity', 'Active');
                $lap->insertBefore($intensity, $lastElementInserted);
                $io->info('Intensity Element added to the Lap element before the TriggerMethod or Track Element');
            }
            $lastElementInserted = $intensity;

            if ($input->hasOption('max-bpm') && $input->getOption('max-bpm')) {
                $valueElement = $xml->createElement('Value', $input->getOption('max-bpm'));
                $maximumBpmElement = $xml->createElement('MaximumHeartRateBpm');
                $maximumBpmElement->appendChild($valueElement);
                $lap->insertBefore($maximumBpmElement, $lastElementInserted);
                $io->info('Optional MaximumHeartRateBpm Element added to the Lap element before the Intensity Element');
                $lastElementInserted = $maximumBpmElement;
            }

            if ($input->hasOption('avg-bpm') && $input->getOption('avg-bpm')) {
                $valueElement = $xml->createElement('Value', $input->getOption('avg-bpm'));
                $avgBpmElement = $xml->createElement('AverageHeartRateBpm');
                $avgBpmElement->appendChild($valueElement);
                $lap->insertBefore($avgBpmElement, $lastElementInserted);
                $io->info('Optional AverageHeartRateBpm Element added to the Lap element before the Intensity or MaximumHeartRateBpm Element');
                $lastElementInserted = $avgBpmElement;
            }

            //add the calories element to the Lap element with the 0 value.
            $calories = $lap->getElementsByTagName('Calories')->item(0);
            if (!$calories) {
                $calories = $xml->createElement('Calories', $caloriesPerLap);
                $lap->insertBefore($calories, $lastElementInserted);
                $io->info("Calories Element added to the Lap element before the Intensity or MaximumHeartRateBpm or AverageHeartRateBpm element");
            }

            $maximumSpeed = $lap->getElementsByTagName('MaximumSpeed')->item(0);
            if (!$maximumSpeed) {
                $maximumSpeed = $xml->createElement('MaximumSpeed', 10.0);
                $lap->insertBefore($maximumSpeed, $calories);
                $io->info("Maximum speed Element added to the Lap element before the Calories element");
            }

        }

        //$xml->schemaValidate($this->garminXsdFile);

        //$io->info('XML file is valid according to the Garmin XSD schema');
        $outputFileName = Uuid::v4() . '.tcx';
        $outputCompleteFileName = $this->resultDirectory . DIRECTORY_SEPARATOR . $outputFileName;
        $xml->save($outputCompleteFileName);
        $io->success(sprintf('XML file saved as %s', $outputCompleteFileName));

        return Command::SUCCESS;
    }
}
