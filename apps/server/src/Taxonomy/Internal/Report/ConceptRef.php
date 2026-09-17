<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Report;

/**
 * A concept named in a report: its stable identifier, its code when it has one, and its label in the
 * fallback locale.
 */
final readonly class ConceptRef
{
    public function __construct(
        public string $uri,
        public ?string $code,
        public string $label,
    ) {}

    /**
     * @return array{uri: string, code: string|null, label: string}
     */
    public function toArray(): array
    {
        return ['uri' => $this->uri, 'code' => $this->code, 'label' => $this->label];
    }
}
