<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Console;

use BothDecks\Taxonomy\Internal\Report\Measures;
use BothDecks\Taxonomy\Internal\Report\Migration;
use BothDecks\Taxonomy\Internal\Report\VersionReport;
use BothDecks\Taxonomy\Internal\Report\VersionSummary;
use Pulsar\Console\Command;
use Pulsar\Console\ExitCode;
use Pulsar\Console\InputInterface;
use Pulsar\Console\OutputInterface;

/**
 * Print the version report: what the current version holds, what changed since the previous one, and the
 * measures. `--json` prints the same as one JSON document, for a file a review can be attached to.
 */
final class ReportCommand extends Command
{
    public function __construct(
        private readonly VersionReport $report,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->name = 'taxonomy:report';
        $this->description = 'Report on the current taxonomy version: contents, migration from the previous one, measures';
        $this->addOption('json', 'Print the report as JSON');
    }

    #[\Override]
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $report = $this->report->build();

        if ($input->getBoolOption('json')) {
            $output->writeln(json_encode($report->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return ExitCode::Success->value;
        }

        $this->printVersion($output, $report->version);
        $output->newLine();

        if ($report->migration !== null) {
            $output->info(\sprintf('Migration from version %d', $report->migration->from));
            $this->printMigration($output, $report->migration);
        } else {
            $output->writeln('No previous version to compare with.');
        }

        $output->newLine();
        $output->info('Measures');
        $this->printMeasures($output, $report->measures);

        return ExitCode::Success->value;
    }

    private function printVersion(OutputInterface $output, VersionSummary $version): void
    {
        $output->info(\sprintf(
            'Version %d: %s %s, snapshot %s taken %s, imported %s%s',
            $version->id,
            $version->source,
            $version->sourceVersion,
            substr($version->digest, 0, 12),
            $version->snapshotTakenAt,
            $version->importedAt,
            $version->current ? ' (current)' : '',
        ));
        $output->writeln(\sprintf('  Licence %s; attribution: %s', $version->licence, $version->attribution));

        foreach ($version->concepts as $kind => $count) {
            $output->writeln(\sprintf('  %-14s %6d', $kind, $count));
        }
    }

    private function printMigration(OutputInterface $output, Migration $migration): void
    {
        foreach ($migration->added as $kind => $added) {
            $output->writeln(\sprintf('  %-14s +%d  -%d', $kind, \count($added), \count($migration->removed[$kind] ?? [])));
        }

        $output->writeln(\sprintf('  preferred labels changed: %d', $migration->preferredLabelsChanged));
        $output->writeln(\sprintf('  skill relations: +%d  -%d', $migration->skillRelationsAdded, $migration->skillRelationsRemoved));

        foreach (['removed' => $migration->removed, 'added' => $migration->added] as $direction => $byKind) {
            foreach ($byKind as $kind => $refs) {
                foreach ($refs as $ref) {
                    $output->writeln(\sprintf('    %s %s: %s %s', $direction, $kind, $ref->code ?? $ref->uri, $ref->label));
                }
            }
        }
    }

    private function printMeasures(OutputInterface $output, Measures $measures): void
    {
        foreach ($measures->coverage as $kind => $perLocale) {
            $output->writeln(\sprintf('  %s: preferred label / description / alternative labels, per language', $kind));

            foreach ($perLocale as $locale => $coverage) {
                $output->writeln(\sprintf(
                    '    %s  %5.1f%%  %5.1f%%  %6d',
                    $locale,
                    100 * $coverage->labelledShare(),
                    100 * $coverage->describedShare(),
                    $coverage->alternativeLabels,
                ));
            }
        }

        $output->writeln(\sprintf(
            '  vectors (%s): %d occupations, %d skills',
            $measures->embeddingModel,
            $measures->vectors['occupation'] ?? 0,
            $measures->vectors['skill'] ?? 0,
        ));

        $output->writeln(\sprintf(
            '  bubbles: %d, of which %d empty; %.2f members on average; %d members added by a person; %d corrections recorded',
            $measures->bubbles,
            $measures->emptyBubbles,
            $measures->meanMembers,
            $measures->membersAddedByAPerson,
            $measures->overridesRecorded,
        ));

        if ($measures->bubbleParameters !== null) {
            $output->writeln('    computed with ' . $measures->bubbleParameters);
        }

        $parts = [];

        foreach ($measures->resolutionsByPath as $path => $n) {
            $parts[] = \sprintf('%s %d', $path, $n);
        }

        $share = $measures->shareAsking();

        $output->writeln(\sprintf(
            '  resolutions logged: %d (%s); share asking the user: %s',
            $measures->resolutions(),
            $parts === [] ? 'none' : implode(', ', $parts),
            $share === null ? 'no resolutions yet' : \sprintf('%.1f%%', 100 * $share),
        ));
    }
}
