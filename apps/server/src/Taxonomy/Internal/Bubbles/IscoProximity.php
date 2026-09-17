<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Bubbles;

/**
 * How close two occupations sit in the international classification, read off their four-digit codes.
 *
 * The code is hierarchical, one digit per level: major group, sub-major group, minor group, unit group.
 * The score is the share of levels the two codes agree on from the top: the same unit group scores one,
 * the same minor group three quarters, and so on down to nothing shared.
 */
final readonly class IscoProximity
{
    private const int LEVELS = 4;

    /**
     * @return float One of 1.0, 0.75, 0.5, 0.25 and 0.0.
     */
    public static function between(string $codeA, string $codeB): float
    {
        $shared = 0;

        while ($shared < self::LEVELS && \strlen($codeA) > $shared && substr($codeA, 0, $shared + 1) === substr($codeB, 0, $shared + 1)) {
            $shared++;
        }

        return $shared / self::LEVELS;
    }
}
