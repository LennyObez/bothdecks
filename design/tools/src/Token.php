<?php

declare(strict_types=1);

namespace BothDecks\Design;

/**
 * One token, with both the value as written and the value after every alias is followed.
 *
 * Both are kept because they answer different questions. The resolved value is what a pixel ends up being;
 * the written value is what the emitters must reproduce, so that `theme.light.brand.fill` renders as
 * `var(--color-brand-600)` and not as a second copy of the number behind it. Emitting the resolved literal
 * everywhere is how one palette becomes five and then drifts apart.
 */
final class Token
{
    /**
     * @param list<string>         $segments   the path, split
     * @param mixed                $written    the value as written in the source, aliases intact
     * @param mixed                $resolved   the value with every `{a.b.c}` replaced
     * @param array<string, mixed> $extensions
     * @param string               $group      the manifest's logical group name
     * @param string               $groupPath  the source prefix that matched
     */
    public function __construct(
        public readonly string $path,
        public readonly array $segments,
        public readonly string $type,
        public readonly mixed $written,
        public readonly mixed $resolved,
        public readonly ?string $description,
        public readonly array $extensions,
        public readonly string $group,
        public readonly string $groupPath,
    ) {
    }

    /** The path this token's value points at, or null when it is a literal. */
    public function aliasTarget(): ?string
    {
        if (is_string($this->written) && preg_match('/^\{([^}]+)\}$/', $this->written, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /** The token's name within its group: `color.brand.600` in group `color` gives `['brand', '600']`. */
    public function localSegments(): array
    {
        return array_slice($this->segments, count(explode('.', $this->groupPath)));
    }

    public function extension(string $key): mixed
    {
        return $this->extensions[$key] ?? null;
    }
}
