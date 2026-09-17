<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Console;

use BothDecks\Taxonomy\Internal\Configuration\TaxonomyConfig;
use BothDecks\Taxonomy\Internal\Embedding\ConceptEmbedder;
use Pulsar\Console\Command;
use Pulsar\Console\ExitCode;
use Pulsar\Console\InputInterface;
use Pulsar\Console\OutputInterface;

/**
 * Give every occupation and skill of the current version a vector, through the configured provider.
 */
final class EmbedCommand extends Command
{
    public function __construct(
        private readonly ConceptEmbedder $embedder,
        private readonly TaxonomyConfig $config,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->name = 'taxonomy:embed';
        $this->description = 'Compute the vector of every occupation and skill that lacks one, with the configured model';
    }

    #[\Override]
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->info(\sprintf(
            'Embedding with %s (%d dimensions) at %s',
            $this->config->embeddingModel,
            $this->config->embeddingDimensions,
            $this->config->embeddingUrl,
        ));

        $lastReported = 0;

        $written = $this->embedder->embedMissing(static function (int $done, int $total) use ($output, &$lastReported): void {
            if ($done - $lastReported >= 500 || $done === $total) {
                $output->writeln(\sprintf('  %d / %d', $done, $total));
                $lastReported = $done;
            }
        });

        $output->success(\sprintf('%d vector(s) written.', $written));

        return ExitCode::Success->value;
    }
}
