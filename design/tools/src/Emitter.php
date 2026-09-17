<?php

declare(strict_types=1);

namespace BothDecks\Design;

/**
 * One target's share of the work.
 *
 * Every emitter records which groups it actually produced output for. That accounting is what the coverage
 * check reads: the question "did elevation reach Swift?" is answered by counting emitted declarations, not
 * by trusting that the emitter meant to.
 */
abstract class Emitter
{
    /** @var array<string, int> group name => declarations emitted */
    private array $produced = [];

    abstract public function target(): string;

    /** @return array<string, string> file name, relative to the output directory, => contents */
    abstract public function emit(TokenSource $source): array;

    /** @return array<string, int> */
    final public function produced(): array
    {
        return $this->produced;
    }

    final protected function record(string $group, int $declarations): void
    {
        if ($declarations < 1) {
            return;
        }

        $this->produced[$group] = ($this->produced[$group] ?? 0) + $declarations;
    }

    /**
     * The header every emitted file carries.
     *
     * It states the source, the command, and the prohibition, and the prohibition is now true, which it
     * was not while the sentence sat on top of files no script produced.
     *
     * It deliberately carries no date and no version: a header that changes on every run makes the
     * "running it twice changes nothing" property untestable, which is the property that lets a pipeline
     * check the committed output against the source.
     */
    final protected function header(string $commentPrefix): string
    {
        $lines = [
            'Generated from design/tokens/tokens.json. Do not edit by hand.',
            '',
            'Run `php design/tools/generate-tokens.php` to rebuild this file, and',
            '`php design/tools/check-tokens.php` to verify that what is committed matches the source.',
            '',
            'A colour, a size, a duration or a curve that is not in the token source does not belong in a',
            'product file either: add it to the source and regenerate.',
        ];

        return implode("\n", array_map(
            static fn (string $line): string => $line === '' ? rtrim($commentPrefix) : $commentPrefix . $line,
            $lines,
        )) . "\n";
    }
}
