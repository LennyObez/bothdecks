<?php

declare(strict_types=1);

namespace BothDecks\Design;

/**
 * The token file, read and made answerable.
 *
 * Responsibilities, in order: parse the JSON, find every token, give each one a type and a group, follow
 * every alias, and refuse the file when any of those cannot be done. Nothing downstream is allowed to guess.
 */
final class TokenSource
{
    /** @var array<string, Token> */
    private array $tokens = [];

    /** @var array<string, string> logical group name => the source prefix that matched */
    private array $matchedPaths = [];

    /** @var array<string, array<string, mixed>> logical group name => the entries read out of an extension */
    private array $extensionGroups = [];

    private function __construct(
        public readonly string $path,
        private readonly array $raw,
        private readonly Manifest $manifest,
    ) {
    }

    public static function load(string $path, Manifest $manifest): self
    {
        if (!is_file($path)) {
            throw new Failure(sprintf('No token source at `%s`.', $path));
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new Failure(sprintf('The token source at `%s` could not be read.', $path));
        }

        try {
            $raw = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new Failure(sprintf('The token source at `%s` is not valid JSON: %s', $path, $e->getMessage()));
        }

        if (!is_array($raw)) {
            throw new Failure(sprintf('The token source at `%s` must be a JSON object.', $path));
        }

        $source = new self($path, $raw, $manifest);
        $source->walk($raw, [], null);
        $source->resolveEverything();
        $source->readExtensionGroups();

        return $source;
    }

    /** @return array<string, Token> keyed by dotted path, in source order */
    public function tokens(): array
    {
        return $this->tokens;
    }

    public function has(string $path): bool
    {
        return isset($this->tokens[$path]);
    }

    public function token(string $path): Token
    {
        return $this->tokens[$path] ?? throw new Failure(sprintf('No token at `%s`.', $path));
    }

    /**
     * Every token in a logical group, in source order.
     *
     * @return array<string, Token>
     */
    public function group(string $group): array
    {
        return array_filter($this->tokens, static fn (Token $t): bool => $t->group === $group);
    }

    public function hasGroup(string $group): bool
    {
        return isset($this->matchedPaths[$group]);
    }

    /** @return array<string, string> */
    public function matchedPaths(): array
    {
        return $this->matchedPaths;
    }

    /** @return array<string, mixed> */
    public function rawDocument(): array
    {
        return $this->raw;
    }

    /**
     * The path a token's value ultimately names, following the whole chain of aliases.
     *
     * One hop is not enough. A role is allowed to point at another role of the same theme, which is how the
     * source says "deliberately the same as this one", and a target that reads only the first hop finds a
     * path that is not a palette entry and falls back to writing the literal out. That literal is a second
     * copy of the number, in a file whose whole purpose is that there is only ever one.
     *
     * Returns null when the token carries a value of its own rather than naming another.
     */
    public function finalAliasOf(Token $token): ?string
    {
        $target = $token->aliasTarget();

        if ($target === null) {
            return null;
        }

        // The loader has already refused every unresolvable reference and every loop, so this terminates.
        while ($this->has($target)) {
            $next = $this->tokens[$target]->aliasTarget();

            if ($next === null) {
                break;
            }

            $target = $next;
        }

        return $target;
    }

    /**
     * The entries of a group the source carries in a root extension rather than as tokens.
     *
     * @return array<string, mixed> entry name => entry, in source order; empty when the group is not
     *                              extension-carried or the extension is absent
     */
    public function extensionEntries(string $group): array
    {
        return $this->extensionGroups[$group] ?? [];
    }

    /** How many things a group holds, whether it is carried as tokens or in an extension. */
    public function groupSize(string $group): int
    {
        return count($this->group($group)) + count($this->extensionEntries($group));
    }

    /**
     * Read the groups the manifest says live in a root extension.
     *
     * A group declared this way is present when its extension is, on the same footing as a group of tokens:
     * the coverage check then holds it to the targets it names, so moving a group out of the token tree
     * cannot be a way of making it stop being required.
     */
    private function readExtensionGroups(): void
    {
        $extensions = $this->raw['$extensions'] ?? [];

        foreach ($this->manifest->groups() as $group => $spec) {
            $declared = $spec['extension'] ?? null;

            if ($declared === null) {
                continue;
            }

            if (!is_array($extensions) || !isset($extensions[$declared['key']])) {
                continue;
            }

            $block = $extensions[$declared['key']];

            if (!is_array($block) || !isset($block[$declared['entries']]) || !is_array($block[$declared['entries']])) {
                throw new Failure(
                    sprintf('The `%s` extension carries no `%s` object.', $declared['key'], $declared['entries']),
                    [sprintf('Group `%s` is read from there, so an extension without it is a group that reaches nothing.', $group)],
                );
            }

            $this->extensionGroups[$group] = $block[$declared['entries']];
            $this->matchedPaths[$group] = '$extensions.' . $declared['key'] . '.' . $declared['entries'];
        }
    }

    /**
     * Depth-first walk. A node is a token when it carries `$value`; anything else with non-`$`-prefixed
     * children is a group, and a group may pass a `$type` down to its descendants.
     */
    private function walk(array $node, array $segments, ?string $inheritedType): void
    {
        $type = isset($node['$type']) && is_string($node['$type']) ? $node['$type'] : $inheritedType;

        if (array_key_exists('$value', $node)) {
            $this->addToken($segments, $node, $type);

            return;
        }

        foreach ($node as $key => $child) {
            if (is_string($key) && str_starts_with($key, '$')) {
                continue;
            }

            if (!is_array($child)) {
                throw new Failure(sprintf(
                    'Node `%s` is neither a token nor a group: it holds a bare %s.',
                    implode('.', [...$segments, (string) $key]),
                    get_debug_type($child),
                ));
            }

            $this->walk($child, [...$segments, (string) $key], $type);
        }
    }

    private function addToken(array $segments, array $node, ?string $type): void
    {
        $path = implode('.', $segments);

        if ($path === '') {
            throw new Failure('The token source declares a `$value` at its root.');
        }

        $match = $this->manifest->groupFor($path);

        if ($match === null) {
            throw new Failure(
                sprintf('Token `%s` belongs to no group the manifest knows.', $path),
                [
                    'Add an entry for it in design/tools/src/Manifest.php naming the targets it must reach.',
                    'A token with no manifest entry is the silent loss this generator exists to prevent, so it is refused rather than skipped.',
                ],
            );
        }

        [$group, $groupPath] = $match;

        // Springs and haptics have no type in the W3C format: it defines neither. The manifest names the
        // type for those groups, so the value is still typed, by a declaration a reader can find rather
        // than by whichever emitter happens to touch it first.
        $type ??= $this->manifest->assumedTypeFor($group);

        if ($type === null) {
            throw new Failure(
                sprintf('Token `%s` has no `$type`, and no ancestor declares one.', $path),
                ['Either give the token a `$type`, or declare the group\'s type in design/tools/src/Manifest.php.'],
            );
        }

        if (isset($this->tokens[$path])) {
            throw new Failure(sprintf('Token `%s` is declared twice.', $path));
        }

        if (isset($this->matchedPaths[$group]) && $this->matchedPaths[$group] !== $groupPath) {
            throw new Failure(sprintf(
                'Group `%s` appears under two names at once, `%s` and `%s`. Pick one.',
                $group,
                $this->matchedPaths[$group],
                $groupPath,
            ));
        }

        $this->matchedPaths[$group] = $groupPath;

        $this->tokens[$path] = new Token(
            $path,
            $segments,
            $type,
            $node['$value'],
            null,
            isset($node['$description']) && is_string($node['$description']) ? $node['$description'] : null,
            isset($node['$extensions']) && is_array($node['$extensions']) ? $node['$extensions'] : [],
            $group,
            $groupPath,
        );
    }

    private function resolveEverything(): void
    {
        foreach ($this->tokens as $path => $token) {
            $this->tokens[$path] = new Token(
                $token->path,
                $token->segments,
                $token->type,
                $token->written,
                $this->resolve($token->written, $path, [$path]),
                $token->description,
                $token->extensions,
                $token->group,
                $token->groupPath,
            );
        }
    }

    /**
     * Replace every `{a.b.c}` with the value it names, following chains and refusing loops.
     *
     * A reference that names nothing is a failure rather than a passthrough: a `var(--x)` with no `--x`
     * behind it renders as nothing at all, which is the class of defect a generator is for.
     */
    private function resolve(mixed $value, string $owner, array $seen): mixed
    {
        if (is_string($value)) {
            if (preg_match('/^\{([^}]+)\}$/', $value, $m) === 1) {
                $target = $m[1];

                if (in_array($target, $seen, true)) {
                    throw new Failure(sprintf('Alias loop: %s.', implode(' -> ', [...$seen, $target])));
                }

                if (!isset($this->tokens[$target])) {
                    throw new Failure(sprintf('Token `%s` references `{%s}`, which no token defines.', $owner, $target));
                }

                return $this->resolve($this->tokens[$target]->written, $target, [...$seen, $target]);
            }

            return $value;
        }

        if (is_array($value)) {
            $out = [];

            foreach ($value as $key => $member) {
                $out[$key] = $this->resolve($member, $owner, $seen);
            }

            return $out;
        }

        return $value;
    }
}
