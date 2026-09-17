<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Report;

/**
 * What changed between two versions: the concepts that appeared and disappeared per kind, the preferred
 * labels that changed in the fallback locale, and the skill relations that came and went.
 *
 * A change here is what a person reviews before the newer version becomes the one profiles and offers are
 * matched against: a removed occupation is a code some profile may carry.
 */
final readonly class Migration
{
    /**
     * @param array<string, list<ConceptRef>> $added By concept kind.
     * @param array<string, list<ConceptRef>> $removed By concept kind.
     */
    public function __construct(
        public int $from,
        public int $to,
        public array $added,
        public array $removed,
        public int $preferredLabelsChanged,
        public int $skillRelationsAdded,
        public int $skillRelationsRemoved,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'added' => self::serialise($this->added),
            'removed' => self::serialise($this->removed),
            'preferred_labels_changed' => $this->preferredLabelsChanged,
            'skill_relations_added' => $this->skillRelationsAdded,
            'skill_relations_removed' => $this->skillRelationsRemoved,
        ];
    }

    /**
     * @param array<string, list<ConceptRef>> $byKind
     *
     * @return array<string, list<array{uri: string, code: string|null, label: string}>>
     */
    private static function serialise(array $byKind): array
    {
        $out = [];

        foreach ($byKind as $kind => $refs) {
            $out[$kind] = [];

            foreach ($refs as $ref) {
                $out[$kind][] = $ref->toArray();
            }
        }

        return $out;
    }
}
