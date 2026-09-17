<?php

declare(strict_types=1);

namespace BothDecks\Design;

/**
 * The other half of the contract: what a design file must contain, written from the source.
 *
 * The three code targets are generated, so they cannot drift. A design file is not. It is edited by hand, in
 * another application, by somebody who is not looking at this repository at the time, and left alone the two
 * halves of one system diverge until a person notices that a screen no longer matches a screenshot.
 *
 * So the source publishes what the file must carry: every variable, in which collection, under which mode,
 * resolved to the value the file must show. Comparing a file against this is a mechanical question with a
 * yes-or-no answer, and the answer names the variables that disagree rather than reporting that something,
 * somewhere, has moved.
 *
 * Two collections are named and deliberately absent, so that their absence is a statement rather than a gap.
 */
final class FigmaExport
{
    /**
     * The design-file collections whose contents come from this source, in the order the file lists them.
     */
    private const array COLLECTIONS = ['1. Primitives', '2. Theme', '3. Scale'];

    /**
     * Groups that reach the design file as bindable numbers. Type styles are styles rather than variables;
     * motion, haptics and elevation reach no variable at all, and the file says so on its own pages.
     */
    private const array SCALE_GROUPS = ['space', 'radius', 'border-width', 'icon', 'control', 'dash', 'type-floor'];

    /**
     * The one path whose design-file name differs from its token path, and why.
     */
    private const string TYPE_FLOOR_PREFIX = 'type.floor.';

    private const string TYPE_FLOOR_NAME = 'typeFloor.';

    /** The exact bytes the repository holds, newline included. */
    public static function build(TokenSource $source): string
    {
        $collections = [];

        foreach (self::COLLECTIONS as $name) {
            $collections[$name] = [
                'modes' => $name === '2. Theme' ? ['Light', 'Dark'] : ['Value'],
                'variables' => [],
            ];
        }

        foreach ($source->group('color') as $token) {
            $collections['1. Primitives']['variables'][self::name($token->path)] = [
                'type' => 'COLOR',
                'values' => ['Value' => Colour::parse($token->resolved, $token->path)->css()],
            ];
        }

        foreach (self::themeRoles($source) as $role => $values) {
            $collections['2. Theme']['variables'][$role] = [
                'type' => 'COLOR',
                'values' => ['Light' => $values['light'], 'Dark' => $values['dark']],
            ];
        }

        foreach (self::SCALE_GROUPS as $group) {
            if (!$source->hasGroup($group)) {
                continue;
            }

            foreach ($source->group($group) as $token) {
                $path = str_starts_with($token->path, self::TYPE_FLOOR_PREFIX)
                    ? self::TYPE_FLOOR_NAME . substr($token->path, strlen(self::TYPE_FLOOR_PREFIX))
                    : $token->path;

                $collections['3. Scale']['variables'][self::name($path)] = [
                    'type' => 'FLOAT',
                    'values' => ['Value' => Value::dimension($token->resolved, $token->path)['value']],
                ];
            }
        }

        foreach ($collections as $name => $collection) {
            ksort($collections[$name]['variables']);
        }

        $document = [
            '$comment' => 'Generated from design/tokens/tokens.json. What the design file must contain, so that '
                . 'the two halves of one system are compared rather than trusted.',
            'notCarriedHere' => [
                '4. Product' => 'The application name has one home in the server configuration and the design '
                    . 'file binds it at import time; the taxonomy source and version are carried in the '
                    . 'source\'s product extension rather than as tokens.',
                '5. Language' => 'The few strings whose length decides a layout, from translation catalogues '
                    . 'that do not exist yet.',
            ],
            'collections' => $collections,
        ];

        $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new Failure('The design-file export could not be encoded.');
        }

        return $json . "\n";
    }

    /**
     * How many variables the export describes, for a command to report rather than assert.
     *
     * @return array<string, int>
     */
    public static function counts(string $json): array
    {
        $document = json_decode($json, true);
        $counts = [];

        foreach ($document['collections'] ?? [] as $name => $collection) {
            $counts[$name] = count($collection['variables'] ?? []);
        }

        return $counts;
    }

    /**
     * Every theme role with both of its values.
     *
     * A role present in one theme and not the other cannot be a design-file variable at all: the file has one
     * variable with two modes, and a missing mode is a variable that resolves to nothing in one theme. The
     * generator already refuses that, so reaching it here is a contradiction worth stopping on.
     *
     * @return array<string, array{light: string, dark: string}>
     */
    private static function themeRoles(TokenSource $source): array
    {
        $roles = [];

        foreach ($source->group('theme') as $token) {
            if ($token->type !== 'color') {
                continue;
            }

            $local = $token->localSegments();
            $mode = (string) array_shift($local);
            $roles[implode('/', $local)][$mode] = Colour::parse($token->resolved, $token->path)->css();
        }

        ksort($roles);

        foreach ($roles as $role => $values) {
            if (!isset($values['light'], $values['dark'])) {
                throw new Failure(
                    sprintf('Role `%s` exists in only one theme, so no design file can carry it.', $role),
                    ['A design-file variable has one entry per mode; a missing mode resolves to nothing in that theme.'],
                );
            }
        }

        return $roles;
    }

    /** Dots separate a token path; the design file groups with slashes. */
    private static function name(string $path): string
    {
        return str_replace('.', '/', $path);
    }
}
