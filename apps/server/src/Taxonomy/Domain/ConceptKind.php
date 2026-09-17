<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Domain;

/**
 * What a taxonomy concept is.
 *
 * Occupations and skills are what the product matches on. The two group kinds are the hierarchies they hang
 * from: the international classification of occupations above the occupations, and the source's own skill
 * hierarchy above the skills. Both are kept because the hierarchy distance between two occupations is one of
 * the signals a bubble is computed from, and a distance needs the tree.
 */
enum ConceptKind: string
{
    case Occupation = 'occupation';
    case Skill = 'skill';
    case IscoGroup = 'isco_group';
    case SkillGroup = 'skill_group';

    /**
     * The kinds a free-text query may resolve to. A group is never the answer to "what is your job".
     *
     * @return list<self>
     */
    public static function resolvable(): array
    {
        return [self::Occupation, self::Skill];
    }
}
