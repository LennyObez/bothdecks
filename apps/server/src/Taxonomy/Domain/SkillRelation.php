<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Domain;

/**
 * How a skill relates to an occupation in the source classification.
 *
 * Essential skills are those the source considers indispensable to the occupation; optional ones are
 * relevant but not required. The distinction carries into matching: an offer's essential skills are what a
 * profile is scored against first, and the skill overlap between two occupations, which is the strongest
 * signal a bubble is computed from, counts essential skills only.
 */
enum SkillRelation: string
{
    case Essential = 'essential';
    case Optional = 'optional';
}
