<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Domain;

/**
 * The outcome of resolving a free-text query to a concept.
 *
 * A resolution either names one concept, with the path that produced it, or asks: it carries the best
 * candidates and no answer. It never guesses, because a code assigned without confidence is a code the
 * matching will trust as if it were certain.
 */
final readonly class Resolution
{
    private ?ResolutionCandidate $answer;

    /**
     * @param list<ResolutionCandidate> $candidates Best first. Exactly one when resolved; up to the configured
     *                                              number when asking; possibly none when nothing came close.
     */
    public function __construct(
        public ResolutionPath $path,
        public array $candidates,
        public int $versionId,
        public string $locale,
        public string $query,
    ) {
        if (!$path->resolved()) {
            $this->answer = null;

            return;
        }

        if (\count($candidates) !== 1) {
            throw new \InvalidArgumentException('A resolved query names exactly one candidate.');
        }

        $this->answer = $candidates[0];
    }

    public function resolved(): ?ResolutionCandidate
    {
        return $this->answer;
    }
}
