<?php

declare(strict_types=1);

namespace BothDecks\Shared\Time;

use Psr\Clock\ClockInterface;

/**
 * The wall clock, in UTC.
 *
 * Every timestamp the product writes goes through the clock interface rather than `new DateTimeImmutable()`,
 * so a test can hold time still and a stored instant never depends on a machine's local zone.
 */
final readonly class SystemClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
