<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Console;

use BothDecks\Taxonomy\Internal\Configuration\TaxonomyConfig;
use BothDecks\Taxonomy\Internal\Esco\EscoSnapshotBuilder;
use Pulsar\Console\Command;
use Pulsar\Console\ExitCode;
use Pulsar\Console\InputInterface;
use Pulsar\Console\OutputInterface;

/**
 * Take a snapshot of the configured source release into the snapshot directory.
 *
 * The release is the one in `config/taxonomy.php`; it is not an option here, because a snapshot of a release
 * the configuration does not name is a snapshot nothing will import.
 */
final class SnapshotCommand extends Command
{
    public function __construct(
        private readonly EscoSnapshotBuilder $builder,
        private readonly TaxonomyConfig $config,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->name = 'taxonomy:snapshot';
        $this->description = 'Fetch every concept of the configured taxonomy release into a verifiable snapshot on disk';
    }

    #[\Override]
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->info(\sprintf(
            'Taking a snapshot of %s %s from %s',
            $this->config->sourceName,
            $this->config->sourceVersion,
            $this->config->sourceApi,
        ));

        $manifest = $this->builder->build(static fn(string $line) => $output->writeln($line));

        $output->success(\sprintf(
            '%d occupations, %d skills, %d occupation groups, %d skill groups. Digest %s.',
            $manifest->records('occupations.ndjson'),
            $manifest->records('skills.ndjson'),
            $manifest->records('isco_groups.ndjson'),
            $manifest->records('skill_groups.ndjson'),
            $manifest->digest(),
        ));

        return ExitCode::Success->value;
    }
}
