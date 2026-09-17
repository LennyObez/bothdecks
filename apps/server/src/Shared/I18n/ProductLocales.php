<?php

declare(strict_types=1);

namespace BothDecks\Shared\I18n;

use Pulsar\Config\I18nConfig;

/**
 * The closed list of locales the product serves, read from the framework's own locale configuration.
 *
 * Every module that keeps text per language asks this object which languages exist. None of them carries a
 * list of its own, and neither does this class: it reads `config/i18n.php` through the framework's typed
 * object, so the locales the framework negotiates and the locales the product stores data in are the same
 * list by construction. Adding a locale is a change to one file.
 */
final readonly class ProductLocales
{
    /**
     * @param list<string> $locales Two-letter codes, sorted, without duplicates.
     * @param string $fallback One of the locales above.
     */
    private function __construct(
        private array $locales,
        private string $fallback,
    ) {}

    /**
     * @throws \InvalidArgumentException when a locale is not a two-letter code, is listed twice, or the
     *                                   default is not among the supported ones. The framework accepts all
     *                                   three; the product does not, because its per-language tables key on
     *                                   the code and its fallback must exist.
     */
    public static function fromFramework(I18nConfig $config): self
    {
        $locales = [];

        foreach ($config->supportedLocales as $locale) {
            if (preg_match('/^[a-z]{2}$/', $locale) !== 1) {
                throw new \InvalidArgumentException(
                    'config/i18n.php lists a locale that is not a two-letter lowercase code: ' . $locale,
                );
            }

            $locales[] = $locale;
        }

        $unique = array_values(array_unique($locales));

        if (\count($unique) !== \count($locales)) {
            throw new \InvalidArgumentException('config/i18n.php lists a locale twice.');
        }

        sort($unique);

        if (!\in_array($config->defaultLocale, $unique, true)) {
            throw new \InvalidArgumentException('config/i18n.php must name a default_locale that is one of the supported ones.');
        }

        return new self($unique, $config->defaultLocale);
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return $this->locales;
    }

    public function fallback(): string
    {
        return $this->fallback;
    }

    public function has(string $locale): bool
    {
        return \in_array($locale, $this->locales, true);
    }

    public function count(): int
    {
        return \count($this->locales);
    }
}
