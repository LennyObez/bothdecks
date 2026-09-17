<?php

declare(strict_types=1);

namespace BothDecks\Design;

/**
 * A generation refused rather than completed.
 *
 * Every path that would produce an artefact the source does not justify raises this. A generator that
 * silently drops a group is the defect this whole tool exists to remove, so there is no "warn and continue"
 * mode: either the source and the manifest agree, or nothing is written.
 */
final class Failure extends \RuntimeException
{
    /** @param list<string> $reasons */
    public function __construct(
        string $headline,
        public readonly array $reasons = [],
    ) {
        $message = $headline;

        foreach ($reasons as $reason) {
            $message .= "\n  - " . $reason;
        }

        parent::__construct($message);
    }
}
