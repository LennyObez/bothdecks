<?php

declare(strict_types=1);

namespace BothDecks\Tests\Unit\Taxonomy\Bubbles;

use BothDecks\Taxonomy\Internal\Bubbles\IscoProximity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(IscoProximity::class)]
final class IscoProximityTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, float}>
     */
    public static function pairs(): iterable
    {
        yield 'the same unit group' => ['2512', '2512', 1.0];
        yield 'the same minor group' => ['2512', '2513', 0.75];
        yield 'the same sub-major group' => ['2512', '2521', 0.5];
        yield 'the same major group' => ['2512', '2221', 0.25];
        yield 'different major groups' => ['2512', '7212', 0.0];
        yield 'nothing to compare' => ['', '', 0.0];
        yield 'a code and none' => ['2512', '', 0.0];
        yield 'more than four levels count as four' => ['25123', '25123', 1.0];
    }

    #[DataProvider('pairs')]
    public function testTheScoreIsTheShareOfLevelsSharedFromTheTop(string $a, string $b, float $expected): void
    {
        // Act
        $score = IscoProximity::between($a, $b);

        // Assert
        self::assertSame($expected, $score);
    }

    #[DataProvider('pairs')]
    public function testItIsSymmetric(string $a, string $b, float $expected): void
    {
        // Act
        $reverse = IscoProximity::between($b, $a);

        // Assert
        self::assertSame($expected, $reverse);
    }
}
