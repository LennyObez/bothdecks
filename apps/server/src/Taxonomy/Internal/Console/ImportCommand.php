<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Console;

use BothDecks\Taxonomy\Internal\Configuration\TaxonomyConfig;
use BothDecks\Taxonomy\Internal\Ingestion\EscoImporter;
use BothDecks\Taxonomy\Internal\Snapshot\Snapshot;
use Pulsar\Console\Command;
use Pulsar\Console\ExitCode;
use Pulsar\Console\InputInterface;
use Pulsar\Console\OutputInterface;

/**
 * Import the snapshot of the configured release as a new taxonomy version, and make it current.
 */
final class ImportCommand extends Command
{
    public function __construct(
        private readonly EscoImporter $importer,
        private readonly TaxonomyConfig $config,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->name = 'taxonomy:import';
        $this->description = 'Load the snapshot of the configured release into the database as a new, current version';
    }

    #[\Override]
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $snapshot = Snapshot::open($this->config->snapshotDirectory());

        $output->info(\sprintf(
            'Importing %s %s, snapshot %s taken %s',
            $snapshot->manifest->source,
            $snapshot->manifest->version,
            $snapshot->manifest->digest(),
            $snapshot->manifest->takenAt->format(\DateTimeInterface::RFC3339),
        ));

        $report = $this->importer->import($snapshot, static fn(string $line) => $output->writeln($line));

        foreach ($report->rows as $table => $count) {
            $output->writeln(\sprintf('  %-22s %8d rows', $table, $count));
        }

        if ($report->labelsSkipped > 0) {
            $output->warning(\sprintf('%d label(s) made of no letters or digits were left out; each is named above.', $report->labelsSkipped));
        }

        $output->success(\sprintf('Version %d imported and current.', $report->versionId));

        return ExitCode::Success->value;
    }
}
