<?php

declare(strict_types=1);

namespace BothDecks\Design;

/**
 * Load the source, run every emitter, refuse anything that does not add up, then write.
 *
 * Nothing is written until every check has passed, so a failed run leaves the tree exactly as it was rather
 * than half-regenerated.
 */
final class Generator
{
    /** @var list<Emitter> */
    private array $emitters;

    public function __construct(
        private readonly string $sourcePath,
        private readonly string $outputDirectory,
    ) {
        $this->emitters = [new CssEmitter(), new KotlinEmitter(), new SwiftEmitter()];
    }

    /**
     * @return array{files: array<string, string>, coverage: list<string>, exclusions: list<string>, pending: list<string>}
     */
    public function build(): array
    {
        $manifest = new Manifest();
        $source = TokenSource::load($this->sourcePath, $manifest);

        $files = [];
        $byTarget = [];

        foreach ($this->emitters as $emitter) {
            foreach ($emitter->emit($source) as $name => $contents) {
                if (isset($files[$name])) {
                    throw new Failure(sprintf('Two emitters both produce `%s`.', $name));
                }

                $files[$name] = $contents;
            }

            $byTarget[$emitter->target()] = $emitter;
        }

        $report = $this->assertCoverage($manifest, $source, $byTarget);
        $this->assertNoNameCollision($files);
        $this->assertNoRemoteReference($files);
        $this->assertHeader($files);

        return [
            'files' => $files,
            'coverage' => $report['coverage'],
            'exclusions' => $report['exclusions'],
            'pending' => $report['pending'],
        ];
    }

    /** @return array{written: list<string>, unchanged: list<string>} */
    public function write(array $files): array
    {
        if (!is_dir($this->outputDirectory) && !mkdir($this->outputDirectory, 0o755, true) && !is_dir($this->outputDirectory)) {
            throw new Failure(sprintf('Could not create `%s`.', $this->outputDirectory));
        }

        $written = [];
        $unchanged = [];

        foreach ($files as $name => $contents) {
            $path = $this->outputDirectory . '/' . $name;

            if (is_file($path) && file_get_contents($path) === $contents) {
                $unchanged[] = $name;

                continue;
            }

            if (file_put_contents($path, $contents) === false) {
                throw new Failure(sprintf('Could not write `%s`.', $path));
            }

            $written[] = $name;
        }

        return ['written' => $written, 'unchanged' => $unchanged];
    }

    /** @return list<string> the files on disk that differ from what the source produces */
    public function differences(array $files): array
    {
        $differences = [];

        foreach ($files as $name => $contents) {
            $path = $this->outputDirectory . '/' . $name;

            if (!is_file($path)) {
                $differences[] = $name . ' (missing)';

                continue;
            }

            if (file_get_contents($path) !== $contents) {
                $differences[] = $name . ' (differs from the source)';
            }
        }

        return $differences;
    }

    /**
     * Both directions of the manifest.
     *
     * @param array<string, Emitter> $byTarget
     *
     * @return array{coverage: list<string>, exclusions: list<string>, pending: list<string>}
     */
    private function assertCoverage(Manifest $manifest, TokenSource $source, array $byTarget): array
    {
        $failures = [];
        $coverage = [];
        $exclusions = [];
        $pending = [];

        foreach ($manifest->groups() as $group => $spec) {
            if (!$source->hasGroup($group)) {
                if ($spec['required']) {
                    $where = array_map(static fn (string $p): string => '`' . $p . '`', $spec['paths']);

                    if ($spec['extension'] !== null) {
                        $where[] = sprintf('`$extensions."%s"`', $spec['extension']['key']);
                    }

                    $failures[] = sprintf(
                        'group `%s` is required and the source has nothing under %s',
                        $group,
                        implode(' or ', $where),
                    );

                    continue;
                }

                $pending[] = sprintf('%s: absent%s', $group, $spec['note'] === null ? '' : '. ' . $spec['note']);

                continue;
            }

            $tokenCount = $source->groupSize($group);

            if ($spec['targets'] === [] && $spec['exclusions'] === []) {
                $failures[] = sprintf('group `%s` reaches no target and declares no reason for it', $group);

                continue;
            }

            $reached = [];

            foreach ($spec['targets'] as $target) {
                $emitter = $byTarget[$target] ?? throw new Failure(sprintf('No emitter for target `%s`.', $target));
                $declarations = $emitter->produced()[$group] ?? 0;

                if ($declarations === 0) {
                    $failures[] = sprintf(
                        'group `%s` (%d token%s) reaches no declaration in the `%s` target',
                        $group,
                        $tokenCount,
                        $tokenCount === 1 ? '' : 's',
                        $target,
                    );

                    continue;
                }

                $reached[] = sprintf('%s %d', $target, $declarations);
            }

            $coverage[] = sprintf('%-16s %2d token%s -> %s', $group, $tokenCount, $tokenCount === 1 ? ' ' : 's', implode(', ', $reached) ?: 'nothing');

            foreach ($spec['exclusions'] as $target => $reason) {
                $exclusions[] = sprintf('%s is not emitted to %s. %s', $group, $target, $reason);
            }
        }

        if ($failures !== []) {
            throw new Failure('A token group does not reach a target it is declared to reach.', $failures);
        }

        return ['coverage' => $coverage, 'exclusions' => $exclusions, 'pending' => $pending];
    }

    /**
     * Two tokens must not flatten to one custom property.
     *
     * `border.focus` is a width and `theme.*.border.focus` is a colour, and both once flattened to
     * `--border-focus`. Whichever was written second won, and the one that lost was the focus ring, the
     * accessibility commitment the other fix was invoking.
     */
    private function assertNoNameCollision(array $files): void
    {
        $seen = [];
        $collisions = [];

        foreach (['foundations.css', 'colors.css'] as $file) {
            if (!isset($files[$file])) {
                continue;
            }

            // Only the first :root block of each file: the later blocks restate the theme roles on purpose.
            $firstBlock = strstr($files[$file], ':root {');
            $firstBlock = $firstBlock === false ? '' : substr($firstBlock, 0, (int) strpos($firstBlock, "\n}"));

            preg_match_all('/^\s*(--[a-z0-9-]+)\s*:/mi', $firstBlock, $matches);

            foreach (array_unique($matches[1]) as $name) {
                if (isset($seen[$name])) {
                    $collisions[] = sprintf('`%s` is declared in both %s and %s', $name, $seen[$name], $file);

                    continue;
                }

                $seen[$name] = $file;
            }
        }

        // Within one file, a repeated name is also a collision.
        foreach (['foundations.css', 'colors.css'] as $file) {
            if (!isset($files[$file])) {
                continue;
            }

            $firstBlock = strstr($files[$file], ':root {');
            $firstBlock = $firstBlock === false ? '' : substr($firstBlock, 0, (int) strpos($firstBlock, "\n}"));

            preg_match_all('/^\s*(--[a-z0-9-]+)\s*:/mi', $firstBlock, $matches);
            $counts = array_count_values($matches[1]);

            foreach ($counts as $name => $count) {
                if ($count > 1) {
                    $collisions[] = sprintf('`%s` is declared %d times in %s', $name, $count, $file);
                }
            }
        }

        if ($collisions !== []) {
            throw new Failure('Two tokens flatten to the same custom property name.', $collisions);
        }
    }

    /**
     * No emitted file may fetch anything from a third party.
     *
     * The previous first line of the first generated file a client consumed was an `@import` to a font
     * service, with a comment underneath saying to self-host instead. A comment is not a mechanism; this is.
     */
    private function assertNoRemoteReference(array $files): void
    {
        $found = [];

        foreach ($files as $name => $contents) {
            foreach (explode("\n", $contents) as $number => $line) {
                if (str_contains($line, '://')) {
                    $found[] = sprintf('%s line %d carries an absolute URL: %s', $name, $number + 1, trim($line));
                }

                // A protocol-relative `url(//host/…)` fetches from the network just as an absolute one does.
                if (preg_match('#url\(\s*["\']?//#i', $line) === 1) {
                    $found[] = sprintf('%s line %d fetches from another host: %s', $name, $number + 1, trim($line));
                }

                if (stripos($line, '@import') !== false) {
                    $found[] = sprintf('%s line %d declares an @import: %s', $name, $number + 1, trim($line));
                }
            }
        }

        if ($found !== []) {
            throw new Failure(
                'An emitted file references something outside the repository.',
                array_merge($found, ['Fonts and every other asset are self-hosted; a remote reference here would send every visitor\'s address to a third party.']),
            );
        }
    }

    private function assertHeader(array $files): void
    {
        $missing = [];

        foreach ($files as $name => $contents) {
            if (!str_contains(substr($contents, 0, 400), 'Do not edit by hand')) {
                $missing[] = $name;
            }
        }

        if ($missing !== []) {
            throw new Failure('An emitted file carries no generated-file header.', $missing);
        }
    }
}
