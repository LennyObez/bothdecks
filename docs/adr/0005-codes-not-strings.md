# ADR-0005: Occupations and skills are codes; text is only a rendering

## Status

Accepted

## Context

The product operates across the 24 official languages of the European Union. A candidate writes their profile
in one language; an offer is published in another. Job boards index strings, which is why a search typed in
Dutch does not find an equivalent offer written in French.

Translating at query time does not fix this. It makes results depend on the quality of a translation performed
at that instant, so the same query can return different results on different days, and no one can explain why a
particular offer did or did not appear.

A further problem sits underneath: exact matching on an occupation code is too narrow to be useful. A senior
full-stack developer who only ever sees offers carrying that exact code sees an almost empty deck, while
offers for website manager, webmaster and software engineer (which they would take) never surface.

## Decision drivers

1. Cross-language search must be predictable, and predictable means explainable.
2. A deck that is empty or wrong on day one loses the user permanently.
3. Widening a search without telling the user destroys the trust that predictability buys.

## Decision

Every occupation, skill, language and level is stored as a stable identifier from a versioned multilingual
taxonomy. Text is never stored as the meaning; it is a label rendered in the reader's language.

**Resolution runs as a cascade, from deterministic to probabilistic, never the reverse.** An exact match on a
preferred or alternative label in any language, after normalisation, handles the large majority of input and is
fully explainable. Then approximate matching for typing errors. Then vector similarity. Beyond a doubt
threshold the system asks the user, offering three candidates with their codes visible, rather than guessing.

Every resolution records the path it took, its score and the taxonomy version.

**Related occupations are grouped into bubbles.** Membership is computed from four weighted signals: hierarchy
distance in the classification; overlap of essential skills, which is the strongest and most explainable
signal because two occupations demanding the same skills are substitutable whatever their titles; vector
similarity of descriptions, which catches synonymy the hierarchy misses; and observed career transitions once
enough data exists. Bubbles are computed offline, versioned and stored (never recomputed per request) and are
asymmetric: a full-stack developer readily accepts a webmaster role, less so the reverse.

Bubbles are inspectable and correctable by hand through an administration screen. No automatic clustering
survives contact with reality unamended.

**The candidate sees their bubble in plain language** in their preferences, as a list of job titles they can
add to or remove from.

Taxonomy versions are explicit. Attachments point to a code and a version. A version migration is deliberate
work producing a report of disappeared, merged and new codes; no attachment is changed silently.

## Alternatives considered

### Free-text search with query-time translation

Rejected. Results become non-reproducible and unexplainable, which is disqualifying for a system that must
justify its recommendations.

### Exact code matching with no bubbles

Rejected. It is predictable and useless: the deck is too thin to sustain the product's central loop.

### Bubbles computed at query time from embeddings

Rejected. It reintroduces non-reproducibility, costs latency on the hottest path, and cannot be reviewed or
corrected before users see its output.

## Consequences

### Positive

- A keyword in any of the 24 languages resolves to the same code as its equivalent in another.
- Every recommendation can name the codes that produced it.
- Labels are translated once at ingestion rather than per query.

### Negative

- Ingestion, versioning and bubble computation are real work with no off-the-shelf substitute.
- Skills absent from the reference taxonomy need a product-owned namespace and a promotion procedure.

### Neutral

- Users occasionally see a disambiguation question. This is a feature: it is the moment the product shows what
  it understood.

## Enforcement

**Planned for M1**, the milestone that builds the taxonomy. Not yet in place.

A test will assert that a set of equivalent keywords across languages resolves to identical codes. A second
will assert that resolution is idempotent. A third will assert that every stored attachment carries a taxonomy
version. A fourth will fail if any user-facing occupation or skill value is stored as free text.

## Security impact

None.

## Privacy impact

Positive. Structured codes replace free text in the fields used for matching, which reduces the incidental
personal detail carried in a searchable field.

## Performance impact

Exact-label resolution is an indexed lookup. Vector search is reached only when the deterministic paths fail.
Bubbles are precomputed, so the hot path reads a stored set rather than computing similarity.

## Migration / rollback plan

Taxonomy versions are additive; a migration report drives any re-attachment. Reverting to free text would lose
cross-language search entirely and is not contemplated.

## Links

- ADR-0002 (framework)
