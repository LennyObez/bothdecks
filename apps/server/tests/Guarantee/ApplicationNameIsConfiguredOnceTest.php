<?php

declare(strict_types=1);

namespace BothDecks\Tests\Guarantee;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Guarantee: the product name reaches the interface from exactly one place.
 *
 * Renaming the product in production must be a configuration change. This test fails if the literal name has
 * leaked into anything that produces user-visible text, because each such occurrence is a rename the
 * configuration change would silently miss, in one of 24 languages, on one of three platforms. That is
 * exactly the class of defect that survives review.
 *
 * The scan walks **every file git tracks**, so it cannot be outgrown by a directory nobody remembered to add
 * to a list. Two categories are then excluded, and the distinction is the point of ADR-0004:
 *
 * - **Immutable technical identifiers.** The PHP namespace, the mobile package identifier and the repository
 *   name cannot change after publication and are not user-visible. They derive from the stable slug rather
 *   than the display name, so PHP is read through the tokenizer: a namespace declaration is ignored, while the
 *   name inside a string is caught.
 * - **Documentation and repository metadata.** Prose about the project may name the project.
 *
 * @see docs/adr/0004-the-product-name-lives-in-one-place.md
 */
#[Group('guarantee')]
final class ApplicationNameIsConfiguredOnceTest extends TestCase
{
    /**
     * The single file permitted to hold the literal name.
     */
    private const string CONFIGURATION_FILE = 'apps/server/config/identity.php';

    /**
     * Extensions whose string literals are read through a tokenizer rather than as raw bytes.
     */
    private const array TOKENIZED_EXTENSIONS = ['php'];

    /**
     * Extensions scanned as plain text, because anything in them can reach a user.
     */
    private const array TEXT_EXTENSIONS = [
        'kt', 'kts', 'swift', 'strings', 'xcstrings', 'plist', 'xib', 'storyboard',
        'html', 'css', 'js', 'ts', 'json', 'yaml', 'yml', 'xml', 'properties', 'txt', 'csv', 'sql',
    ];

    /**
     * Paths excluded from the scan, each for a stated reason.
     *
     * @var array<string, string>
     */
    private const array EXCLUDED_PREFIXES = [
        'docs/' => 'documentation may name the project',
        '.github/' => 'repository metadata carries the repository name, an immutable identifier',
        'apps/server/tests/' => 'test code is never rendered to a user, and a test of the naming mechanism has to be able to name it',
    ];

    /**
     * Tracked files excluded by exact name, each for a stated reason.
     *
     * @var array<string, string>
     */
    private const array EXCLUDED_FILES = [
        'README.md' => 'documentation may name the project',
        'CHANGELOG.md' => 'documentation may name the project',
        'CONTRIBUTING.md' => 'documentation may name the project',
        'CODE_OF_CONDUCT.md' => 'documentation may name the project',
        'SECURITY.md' => 'documentation may name the project',
        'LICENSE' => 'the licence names the work it covers',
        'apps/server/composer.json' => 'the package name is an immutable technical identifier',
        'apps/server/composer.lock' => 'generated, and it records the package name',
    ];

    public function testTheProductNameReachesTheInterfaceOnlyFromItsConfigurationValue(): void
    {
        // Arrange
        $name = self::configuredName();

        // Act
        $offenders = self::trackedFilesMentioningName($name);

        // Assert
        self::assertSame(
            [],
            $offenders,
            \sprintf(
                "The product name \"%s\" must reach the interface only from %s, so that a rename is a "
                . "configuration change. Read it from the application identity instead.\nFound in:\n  - %s",
                $name,
                self::CONFIGURATION_FILE,
                implode("\n  - ", $offenders),
            ),
        );
    }

    public function testEveryTrackedSourceFileIsInsideTheScan(): void
    {
        // The guarantee is worth exactly what the scan reaches, and a count threshold would be an arbitrary
        // number that ages badly. This compares the scan against what git actually tracks, so a file type or
        // a directory the scan stopped matching shows up by name.

        // Act
        $unscanned = self::trackedSourceFilesOutsideTheScan();

        // Assert
        self::assertSame(
            [],
            $unscanned,
            "These files are tracked and can carry user-visible text, but the scan does not reach them.\n"
            . "Add their extension to TEXT_EXTENSIONS, or exclude them explicitly with a stated reason:\n  - "
            . implode("\n  - ", $unscanned),
        );
    }

    public function testTheConfigurationFileIsInsideTheScanSoItsExemptionIsLive(): void
    {
        // If the configuration file fell outside the scan, its exemption would be dead code and any other
        // file could carry the name unnoticed.

        // Act
        $scanned = self::scannedFiles();

        // Assert
        self::assertNotEmpty($scanned, 'The scan matched no files at all; its configuration is wrong.');
        self::assertContains(self::CONFIGURATION_FILE, $scanned);
    }

    public function testTheTokenizerSeesAStringLiteralButNotANamespace(): void
    {
        // The guarantee rests entirely on this distinction, so it is tested directly rather than assumed.

        // Arrange
        // The fixture name is arbitrary and deliberately not the product's own, so this test proves the
        // mechanism rather than depending on what the product happens to be called today.
        $fixtureName = 'Placeholder';
        $namespaceOnly = "<?php\nnamespace {$fixtureName}\\Shared;\nfinal class A {}\n";
        $stringLiteral = "<?php\nnamespace Other\\Place;\n\$greeting = 'Welcome to {$fixtureName}';\n";

        // Act
        $namespaceHit = self::sourceMentionsNameInAStringLiteral($namespaceOnly, $fixtureName);
        $literalHit = self::sourceMentionsNameInAStringLiteral($stringLiteral, $fixtureName);

        // Assert
        self::assertFalse($namespaceHit, 'A namespace declaration must not count as a user-visible mention.');
        self::assertTrue($literalHit, 'A string literal must count as a user-visible mention.');
    }

    public function testTheConfigurationFileReallyHoldsTheName(): void
    {
        // The exemption must be earned. If the configuration file stopped holding the name, the guarantee
        // above would pass trivially while the name lived somewhere unscanned.

        // Arrange
        $path = self::repositoryRoot() . '/' . self::CONFIGURATION_FILE;

        // Act
        $source = file_get_contents($path);

        // Assert
        self::assertIsString($source);
        self::assertTrue(
            self::sourceMentionsNameInAStringLiteral($source, self::configuredName()),
            'The configuration file must hold the product name as a string literal.',
        );
    }

    /**
     * Every tracked file that mentions the name in text a user could see.
     *
     * @return list<string>
     */
    private static function trackedFilesMentioningName(string $name): array
    {
        $offenders = [];

        foreach (self::scannedFiles() as $relativePath) {
            if ($relativePath === self::CONFIGURATION_FILE) {
                continue;
            }

            if (self::fileMentionsNameInUserVisibleText(self::repositoryRoot() . '/' . $relativePath, $name)) {
                $offenders[] = $relativePath;
            }
        }

        return $offenders;
    }

    /**
     * Tracked files that can carry user-visible text but that the scan does not reach.
     *
     * Anything git tracks is published. A tracked file is therefore either scanned, or excluded by an entry
     * that states why; there is no third category, and this returns whatever falls into one.
     *
     * @return list<string>
     */
    private static function trackedSourceFilesOutsideTheScan(): array
    {
        $extensions = [...self::TOKENIZED_EXTENSIONS, ...self::TEXT_EXTENSIONS];
        $scanned = self::scannedFiles();
        $unscanned = [];

        foreach (self::trackedFiles() as $relativePath) {
            if (\in_array($relativePath, $scanned, true)) {
                continue;
            }

            if (self::isExplicitlyExcluded($relativePath)) {
                continue;
            }

            if (\in_array(strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)), $extensions, true)) {
                $unscanned[] = $relativePath;
            }
        }

        sort($unscanned);

        return $unscanned;
    }

    private static function isExplicitlyExcluded(string $relativePath): bool
    {
        if (isset(self::EXCLUDED_FILES[$relativePath])) {
            return true;
        }

        foreach (array_keys(self::EXCLUDED_PREFIXES) as $prefix) {
            if (str_starts_with($relativePath, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tracked files whose contents can reach a user, as repository-relative paths.
     *
     * @return list<string>
     */
    private static function scannedFiles(): array
    {
        $extensions = [...self::TOKENIZED_EXTENSIONS, ...self::TEXT_EXTENSIONS];
        $scanned = [];

        foreach (self::trackedFiles() as $relativePath) {
            if (!self::isScannable($relativePath, $extensions)) {
                continue;
            }

            $scanned[] = $relativePath;
        }

        sort($scanned);

        return $scanned;
    }

    /**
     * @param list<string> $extensions
     */
    private static function isScannable(string $relativePath, array $extensions): bool
    {
        if (isset(self::EXCLUDED_FILES[$relativePath])) {
            return false;
        }

        foreach (array_keys(self::EXCLUDED_PREFIXES) as $prefix) {
            if (str_starts_with($relativePath, $prefix)) {
                return false;
            }
        }

        return \in_array(strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)), $extensions, true);
    }

    /**
     * Every file git tracks, as repository-relative paths with forward slashes on every platform.
     *
     * @return list<string>
     */
    private static function trackedFiles(): array
    {
        $command = \sprintf('git -C %s ls-files', escapeshellarg(self::repositoryRoot()));
        $output = [];
        $status = 0;

        exec($command . ' 2>/dev/null', $output, $status);

        if ($status !== 0 || $output === []) {
            self::fail(
                'Could not list tracked files with git. This guarantee scans what the repository publishes, '
                . 'so it needs a working tree with a git history.',
            );
        }

        return array_values(array_filter($output, static fn(string $line): bool => $line !== ''));
    }

    private static function fileMentionsNameInUserVisibleText(string $absolutePath, string $name): bool
    {
        $contents = file_get_contents($absolutePath);

        if (!\is_string($contents)) {
            return false;
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        if (\in_array($extension, self::TOKENIZED_EXTENSIONS, true)) {
            return self::sourceMentionsNameInAStringLiteral($contents, $name);
        }

        return stripos($contents, $name) !== false;
    }

    /**
     * Read PHP string literals through the tokenizer, so identifiers and namespaces are not mistaken for text.
     */
    private static function sourceMentionsNameInAStringLiteral(string $source, string $name): bool
    {
        $literalTokens = [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML];

        foreach (token_get_all($source) as $token) {
            if (!\is_array($token)) {
                continue;
            }

            [$id, $text] = $token;

            if (\in_array($id, $literalTokens, true) && stripos($text, $name) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return non-empty-string
     */
    private static function configuredName(): string
    {
        $config = require self::repositoryRoot() . '/' . self::CONFIGURATION_FILE;

        if (!\is_array($config)) {
            self::fail(self::CONFIGURATION_FILE . ' must return an array.');
        }

        $name = $config['name'] ?? null;

        if (!\is_string($name) || $name === '') {
            self::fail(self::CONFIGURATION_FILE . ' must define a non-empty "name".');
        }

        return $name;
    }

    private static function repositoryRoot(): string
    {
        return \dirname(__DIR__, 4);
    }
}
