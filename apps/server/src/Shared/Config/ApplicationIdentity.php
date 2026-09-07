<?php

declare(strict_types=1);

namespace BothDecks\Shared\Config;

/**
 * The application's identity, read from configuration.
 *
 * This is the only type in the product that knows the product's name. Every surface that displays the name
 * asks this object; none of them writes the literal. That is what makes the rename a configuration change
 * rather than a refactor, and a guarantee test fails the build if a literal appears elsewhere.
 *
 * @see docs/adr/0004-the-product-name-lives-in-one-place.md
 */
final readonly class ApplicationIdentity
{
    /**
     * The environments the application recognises, matching the framework's own set.
     *
     * The list is closed on purpose. With an open set, `isProduction()` answers false for anything it does not
     * recognise, so a misspelling in a deployment's configuration quietly relaxes every control that hardens
     * for production: a failure that is invisible until it matters.
     *
     * @var list<non-empty-string>
     */
    private const array ENVIRONMENTS = ['local', 'testing', 'staging', 'production'];

    /**
     * Every invariant is checked here rather than in the factory below.
     *
     * A `non-empty-string` annotation is a claim the language does not enforce: `new self('', '', '', '')`
     * satisfies the signature and produces an object that renders as blank space. Validating in the
     * constructor means no caller, including a test, can build an instance that breaks the guarantees the
     * rest of the product reads from it.
     *
     * The parameters are typed as plain strings on purpose. `non-empty-string` is a static claim the language
     * cannot enforce and the analyser can only propagate: it moves the obligation to every caller instead of
     * settling it here. The runtime check below is what actually holds, and it is tested.
     *
     * @param string $name User-visible product name; changes on rebrand.
     * @param string $slug Stable technical identifier; never changes after publication.
     * @param string $url Canonical public origin, used to build absolute URLs.
     * @param string $environment Active environment name, from the closed set above.
     *
     * @throws \InvalidArgumentException when a value is empty, malformed, or outside its allowed set.
     */
    public function __construct(
        private string $name,
        private string $slug,
        private string $url,
        private string $environment,
    ) {
        self::requireNonEmpty('name', $name);
        self::requireNonEmpty('slug', $slug);
        self::requireAbsoluteUrl('url', $url);
        self::requireKnownEnvironment($environment);
    }

    /**
     * Build the identity from the identity configuration array.
     *
     * The parameter is deliberately typed as an arbitrary array. It is fed the return value of a
     * configuration file, whose shape nothing guarantees, and every key is validated below rather than
     * assumed. A narrower signature would only move the unchecked assumption to the caller.
     *
     * @param array<mixed, mixed> $config
     *
     * @throws \InvalidArgumentException when a key is missing, empty, of the wrong type, or out of range.
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            self::stringAt($config, 'name'),
            self::stringAt($config, 'slug'),
            self::stringAt($config, 'url'),
            self::stringAt($config, 'env'),
        );
    }

    /**
     * Read a configuration value as a string, leaving every other invariant to the constructor.
     *
     * @param array<mixed, mixed> $config
     */
    private static function stringAt(array $config, string $key): string
    {
        $value = $config[$key] ?? null;

        if (!\is_string($value)) {
            throw new \InvalidArgumentException(
                \sprintf('Application configuration key "%s" must be a non-empty string.', $key),
            );
        }

        return $value;
    }

    private static function requireNonEmpty(string $key, string $value): void
    {
        if ($value === '') {
            throw new \InvalidArgumentException(
                \sprintf('Application configuration key "%s" must be a non-empty string.', $key),
            );
        }
    }

    private static function requireAbsoluteUrl(string $key, string $value): void
    {
        self::requireNonEmpty($key, $value);

        $parts = parse_url($value);

        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException(\sprintf(
                'Application configuration key "%s" must be an absolute URL with a scheme and a host, got "%s".',
                $key,
                $value,
            ));
        }

        if (!\in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new \InvalidArgumentException(\sprintf(
                'Application configuration key "%s" must use http or https, got "%s".',
                $key,
                $parts['scheme'],
            ));
        }
    }

    private static function requireKnownEnvironment(string $value): void
    {
        self::requireNonEmpty('env', $value);

        if (!\in_array($value, self::ENVIRONMENTS, true)) {
            throw new \InvalidArgumentException(\sprintf(
                'Application configuration key "env" must be one of %s, got "%s".',
                implode(', ', self::ENVIRONMENTS),
                $value,
            ));
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function environment(): string
    {
        return $this->environment;
    }

    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }
}
