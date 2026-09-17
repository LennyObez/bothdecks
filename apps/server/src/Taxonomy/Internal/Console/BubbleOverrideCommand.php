<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Console;

use BothDecks\Taxonomy\Domain\ConceptKind;
use BothDecks\Taxonomy\Internal\Bubbles\BubbleComputer;
use BothDecks\Taxonomy\Internal\Catalogue\ConceptFinder;
use Psr\Clock\ClockInterface;
use Pulsar\Console\Command;
use Pulsar\Console\ExitCode;
use Pulsar\Console\InputInterface;
use Pulsar\Console\OutputInterface;
use Pulsar\Database\ConnectionInterface;

/**
 * Record a person's correction to a bubble, and recompute that one bubble with it applied.
 *
 * A correction is keyed by the source identifiers of the two occupations, so it survives a recomputation
 * and a version change, and it carries a reason and an author, because a correction nobody can explain
 * later is one the next person will undo.
 */
final class BubbleOverrideCommand extends Command
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly ConceptFinder $finder,
        private readonly BubbleComputer $computer,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->name = 'taxonomy:bubble:override';
        $this->description = 'Add a neighbour to an occupation\'s bubble, or exclude one, with a reason, and recompute that bubble';
        $this->addOption('occupation', 'The occupation whose bubble is corrected, by code or identifier');
        $this->addOption('neighbour', 'The neighbouring occupation, by code or identifier');
        $this->addOption('action', '"include" or "exclude"');
        $this->addOption('weight', 'With include: the weight to give the neighbour, between 0 and 1; computed when omitted');
        $this->addOption('reason', 'Why, in one sentence');
        $this->addOption('author', 'Who decided');
    }

    #[\Override]
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $occupation = $this->finder->find(ConceptKind::Occupation, $input->getStringOption('occupation'));
        $neighbour = $this->finder->find(ConceptKind::Occupation, $input->getStringOption('neighbour'));
        $action = $input->getStringOption('action');
        $reason = trim($input->getStringOption('reason'));
        $author = trim($input->getStringOption('author'));
        $weightOption = $input->getNullableStringOption('weight');

        if ($occupation === null || $neighbour === null) {
            $output->errorln('Both --occupation and --neighbour must name an occupation of the current version, by code or identifier.');

            return ExitCode::Error->value;
        }

        if ($occupation['id'] === $neighbour['id']) {
            $output->errorln('An occupation is not its own neighbour.');

            return ExitCode::Error->value;
        }

        if (!\in_array($action, ['include', 'exclude'], true)) {
            $output->errorln('--action is "include" or "exclude".');

            return ExitCode::Error->value;
        }

        if ($reason === '' || $author === '') {
            $output->errorln('--reason and --author are required: a correction nobody can explain is one the next person undoes.');

            return ExitCode::Error->value;
        }

        $weight = null;

        if ($weightOption !== null) {
            if ($action !== 'include' || !is_numeric($weightOption) || (float) $weightOption < 0.0 || (float) $weightOption > 1.0) {
                $output->errorln('--weight goes with include and is between 0 and 1.');

                return ExitCode::Error->value;
            }

            $weight = round((float) $weightOption, 4);
        }

        $this->connection->execute(
            <<<'SQL'
                INSERT INTO occupation_cluster_override (occupation_uri, neighbour_uri, action, weight, reason, author, created_at)
                VALUES (:o, :n, :action, :weight, :reason, :author, :at)
                ON CONFLICT (occupation_uri, neighbour_uri)
                DO UPDATE SET action = EXCLUDED.action, weight = EXCLUDED.weight, reason = EXCLUDED.reason, author = EXCLUDED.author, created_at = EXCLUDED.created_at
                SQL,
            [
                'o' => $occupation['uri'],
                'n' => $neighbour['uri'],
                'action' => $action,
                'weight' => $weight,
                'reason' => $reason,
                'author' => $author,
                'at' => $this->clock->now()->format(\DateTimeInterface::RFC3339),
            ],
        );

        $this->computer->compute(static fn(string $line) => $output->writeln($line), $occupation['id']);

        $output->success(\sprintf(
            '%s (%s) now %s %s (%s) in its bubble; recorded by %s.',
            $occupation['label'],
            $occupation['code'] ?? $occupation['uri'],
            $action === 'include' ? 'includes' : 'excludes',
            $neighbour['label'],
            $neighbour['code'] ?? $neighbour['uri'],
            $author,
        ));

        return ExitCode::Success->value;
    }
}
