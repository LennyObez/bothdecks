<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Report;

/**
 * How the current version is doing: what each language covers, what has a vector, what the bubbles look
 * like, and how often the cascade had to ask.
 */
final readonly class Measures
{
    /**
     * @param array<string, array<string, LocaleCoverage>> $coverage By concept kind, then locale.
     * @param array<string, int> $vectors Concepts with a vector from the configured model, by kind.
     * @param array<string, int> $resolutionsByPath Log rows per path.
     */
    public function __construct(
        public array $coverage,
        public string $embeddingModel,
        public array $vectors,
        public int $bubbles,
        public int $emptyBubbles,
        public float $meanMembers,
        public int $membersAddedByAPerson,
        public int $overridesRecorded,
        public ?string $bubbleParameters,
        public array $resolutionsByPath,
    ) {}

    public function resolutions(): int
    {
        return array_sum($this->resolutionsByPath);
    }

    /**
     * The share of resolutions that asked the user, or null before any resolution was logged.
     */
    public function shareAsking(): ?float
    {
        $total = $this->resolutions();

        return $total === 0 ? null : round(($this->resolutionsByPath['ask'] ?? 0) / $total, 4);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'coverage' => array_map(
                static fn(array $perLocale): array => array_map(static fn(LocaleCoverage $c): array => $c->toArray(), $perLocale),
                $this->coverage,
            ),
            'vectors' => ['model' => $this->embeddingModel] + $this->vectors,
            'bubbles' => [
                'bubbles' => $this->bubbles,
                'empty' => $this->emptyBubbles,
                'mean_members' => $this->meanMembers,
                'added_by_a_person' => $this->membersAddedByAPerson,
                'overrides_recorded' => $this->overridesRecorded,
                'parameters' => $this->bubbleParameters,
            ],
            'resolutions' => [
                'total' => $this->resolutions(),
                'by_path' => $this->resolutionsByPath,
                'share_asking' => $this->shareAsking(),
            ],
        ];
    }
}
