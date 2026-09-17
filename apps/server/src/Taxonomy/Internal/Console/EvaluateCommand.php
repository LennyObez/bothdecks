<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Console;

use BothDecks\Taxonomy\Domain\ConceptKind;
use BothDecks\Taxonomy\Internal\Report\EvaluationTally;
use BothDecks\Taxonomy\Internal\Report\ResolutionEvaluation;
use Pulsar\Console\Command;
use Pulsar\Console\ExitCode;
use Pulsar\Console\InputInterface;
use Pulsar\Console\OutputInterface;

/**
 * Measure the cascade per language: on held-out alternative labels of the current version, and on the
 * annotated queries shipped with the product.
 */
final class EvaluateCommand extends Command
{
    private const int DEFAULT_SAMPLE = 100;
    private const int DEFAULT_SEED = 20260917;

    public function __construct(
        private readonly ResolutionEvaluation $evaluation,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->name = 'taxonomy:evaluate';
        $this->description = 'Measure resolution precision per language, on held-out labels and on annotated queries';
        $this->addOption('sample', 'Held-out alternative labels per language', null, (string) self::DEFAULT_SAMPLE);
        $this->addOption('seed', 'Seed of the held-out sample, so a run can be repeated', null, (string) self::DEFAULT_SEED);
        $this->addOption('kind', '"occupation" or "skill" for the held-out sample', null, 'occupation');
        $this->addOption('json', 'Print the figures as JSON');
    }

    #[\Override]
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $sample = $input->getIntOption('sample', self::DEFAULT_SAMPLE);
        $kind = ConceptKind::tryFrom($input->getStringOption('kind', 'occupation'));

        if ($sample < 1 || $kind === null || !\in_array($kind, ConceptKind::resolvable(), true)) {
            $output->errorln('--sample is a positive number and --kind is "occupation" or "skill".');

            return ExitCode::Error->value;
        }

        $queries = self::annotatedQueries();

        $heldOut = $this->evaluation->heldOut($kind, $sample, $input->getIntOption('seed', self::DEFAULT_SEED));
        $annotated = $this->evaluation->annotated($queries);

        if ($input->getBoolOption('json')) {
            $output->writeln(json_encode([
                'held_out' => ['kind' => $kind->value, 'sample_per_locale' => $sample, 'by_locale' => array_map(static fn(EvaluationTally $t): array => $t->toArray(), $heldOut)],
                'annotated' => ['queries' => \count($queries), 'by_locale' => array_map(static fn(EvaluationTally $t): array => $t->toArray(), $annotated)],
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return ExitCode::Success->value;
        }

        $output->info(\sprintf('Held out: %d alternative %s labels per language, removed then resolved', $sample, $kind->value));
        $this->printTable($output, $heldOut);
        $output->newLine();
        $output->info(\sprintf('Annotated: %d queries written by a person', \count($queries)));
        $this->printTable($output, $annotated);

        return ExitCode::Success->value;
    }

    /**
     * @param array<string, EvaluationTally> $tallies
     */
    private function printTable(OutputInterface $output, array $tallies): void
    {
        $output->writeln('  lang   n   right  wrong  asked+  asked-   precision   recall   asking   wrong');

        foreach ($tallies as $locale => $tally) {
            $output->writeln(\sprintf(
                '  %-4s %4d  %5d  %5d  %6d  %6d   %9s  %7s  %7s  %6s',
                $locale,
                $tally->total(),
                $tally->right(),
                $tally->wrong(),
                $tally->askedWithRight(),
                $tally->askedWithoutRight(),
                self::percent($tally->precision()),
                self::percent($tally->recall()),
                self::percent($tally->askShare()),
                self::percent($tally->wrongShare()),
            ));
        }
    }

    private static function percent(?float $value): string
    {
        return $value === null ? 'n/a' : \sprintf('%.1f%%', 100 * $value);
    }

    /**
     * @return list<array{query: string, locale: string, kind: string, code: string}>
     */
    private static function annotatedQueries(): array
    {
        $path = \dirname(__DIR__, 4) . '/resources/taxonomy/annotated-queries.json';
        $raw = file_get_contents($path);

        if (!\is_string($raw)) {
            throw new \RuntimeException('Cannot read ' . $path);
        }

        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $entries = \is_array($decoded) ? ($decoded['queries'] ?? null) : null;

        if (!\is_array($entries)) {
            throw new \RuntimeException($path . ' must hold a "queries" list.');
        }

        $queries = [];

        foreach ($entries as $index => $entry) {
            if (!\is_array($entry)
                || !\is_string($entry['query'] ?? null) || !\is_string($entry['locale'] ?? null)
                || !\is_string($entry['kind'] ?? null) || !\is_string($entry['code'] ?? null)
            ) {
                throw new \RuntimeException(\sprintf('%s: query %s is malformed.', $path, (string) $index));
            }

            $queries[] = ['query' => $entry['query'], 'locale' => $entry['locale'], 'kind' => $entry['kind'], 'code' => $entry['code']];
        }

        return $queries;
    }
}
