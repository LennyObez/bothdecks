<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Console;

use BothDecks\Taxonomy\Internal\Bubbles\BubbleComputer;
use Pulsar\Console\Command;
use Pulsar\Console\ExitCode;
use Pulsar\Console\InputInterface;
use Pulsar\Console\OutputInterface;

/**
 * Compute every occupation's bubble for the current version, from the four signals, and store it.
 */
final class BubblesCommand extends Command
{
    public function __construct(
        private readonly BubbleComputer $computer,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->name = 'taxonomy:bubbles';
        $this->description = 'Compute and store the bubble of neighbouring occupations around every occupation';
    }

    #[\Override]
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $report = $this->computer->compute(static fn(string $line) => $output->writeln($line));

        $output->success(\sprintf(
            '%d bubbles, %d members (%d added and %d excluded by a person).',
            $report->occupations,
            $report->members,
            $report->added,
            $report->excluded,
        ));

        foreach ($report->weights as $signal => $weight) {
            if ($weight === 0.0) {
                $output->warning(\sprintf('The %s signal had no data for this version and took weight zero.', $signal));
            }
        }

        return ExitCode::Success->value;
    }
}
