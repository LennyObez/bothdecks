<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Console;

use BothDecks\Taxonomy\Contracts\TaxonomyResolver;
use BothDecks\Taxonomy\Domain\ConceptKind;
use Pulsar\Console\Command;
use Pulsar\Console\ExitCode;
use Pulsar\Console\InputInterface;
use Pulsar\Console\OutputInterface;

/**
 * Resolve one free-text query at the terminal, showing the path taken and the candidates, the way the
 * product will.
 */
final class ResolveCommand extends Command
{
    public function __construct(
        private readonly TaxonomyResolver $resolver,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->name = 'taxonomy:resolve';
        $this->description = 'Resolve free text to an occupation or a skill and show how';
        $this->addArgument('query', 'The text to resolve', true);
        $this->addOption('locale', 'The language of the text', null, 'en');
        $this->addOption('kind', '"occupation" or "skill"', null, 'occupation');
    }

    #[\Override]
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        // Arguments are positional in the console's input; the first one after the command name is the text.
        $query = $input->getArgument(0);

        if (!\is_string($query) || trim($query) === '') {
            $output->errorln('Give the text to resolve as the first argument.');

            return ExitCode::Error->value;
        }

        $kind = ConceptKind::tryFrom($input->getStringOption('kind', 'occupation'));

        if ($kind === null || !\in_array($kind, ConceptKind::resolvable(), true)) {
            $output->errorln('--kind is "occupation" or "skill".');

            return ExitCode::Error->value;
        }

        $resolution = $this->resolver->resolve($query, $input->getStringOption('locale', 'en'), $kind);
        $answer = $resolution->resolved();

        if ($answer !== null) {
            $output->success(\sprintf(
                '%s  %s  (%s, score %.3f, version %d)',
                $answer->code ?? $answer->uri,
                $answer->label,
                $resolution->path->value,
                $answer->score,
                $resolution->versionId,
            ));

            return ExitCode::Success->value;
        }

        $output->warning(\sprintf('No confident answer (version %d). The user would be asked, with:', $resolution->versionId));

        foreach ($resolution->candidates as $candidate) {
            $output->writeln(\sprintf('  %-10s %-50s %.3f', $candidate->code ?? '', $candidate->label, $candidate->score));
        }

        if ($resolution->candidates === []) {
            $output->writeln('  (nothing came close)');
        }

        return ExitCode::Success->value;
    }
}
