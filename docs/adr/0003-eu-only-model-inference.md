# ADR-0003: Model inference runs only on infrastructure under European law

## Status

Accepted

## Context

The product uses language models at runtime for three tasks: extracting structure from an uploaded CV,
normalising an imported job offer, and classifying user-submitted text before publication. CV content is
personal data belonging to people who are often in a precarious position.

Hosting, storage, content delivery and mail were already constrained to providers under European law. The
question was whether model inference could be exempted, given that the most capable general-purpose models are
operated from the United States.

The leading first-party API was examined directly. Its data-residency documentation states that the inference
geography parameter accepts two values, a global routing option and a United States option, and that the
workspace geography governing storage at rest is available only as United States. There is no European
inference geography and no European workspace geography.

Consequently, sending CV content to that API would constitute a transfer of personal data to a third country
under Chapter V of the General Data Protection Regulation, requiring a transfer mechanism, a transfer impact
assessment, and an argument about whether pseudonymised text remains personal data in the recipient's hands.

## Decision drivers

1. The audience is job seekers; their CVs are among the most revealing documents a person produces.
2. A compliance chapter that can be removed by an architectural choice is better than one that must be
   defended in writing every year.
3. European hosting is a commercial argument in this market, not only a legal constraint.

## Decision

All model inference at runtime runs on infrastructure operated under European law. No inference request leaves
the Union.

The framework's provider abstraction is used, with implementations for European managed inference and for
self-hosted open-weight models. Provider choice is decided by measurement on a per-language reference set, not
by reputation: a provider is adopted only when its field-level precision and recall are recorded across the
languages the product ships in.

Two model profiles are needed and are chosen independently: an instruction-following multilingual model for
structured extraction, and a multilingual embedding model for retrieval.

Content minimisation remains in force regardless. A document is segmented before any call, only the segments
the task requires are sent, and direct identifiers are removed first. If the identifier detector does not
complete, the call does not happen and the user is offered manual entry: the failure is closed, not silent.

**This decision concerns runtime only.** Development tooling that reads source code is a different activity
involving no candidate data, and is out of scope here.

## Alternatives considered

### Non-European inference with pseudonymisation as the safeguard

Rejected. Pseudonymisation is a genuine minimisation measure but a contested basis for lawful transfer, and a
CV's free text can re-identify a person through a rare job title combined with an employer and a date range.
Building the product's central compliance argument on an unsettled legal question is not a foundation.

### Splitting tasks by sensitivity, with non-personal tasks sent outside the Union

Rejected. It doubles the provider surface, doubles the evaluation work, and creates a classification boundary
that must be correct on every call forever. The failure mode is a routing bug that sends a CV down the wrong
path, which is exactly the kind of error no test catches until it matters.

## Consequences

### Positive

- No transfer to a third country, so no transfer mechanism and no transfer impact assessment to maintain.
- The sovereignty claim is uniform across the whole stack and can be stated without qualification.

### Negative

- The choice of models is narrower, and quality on the less-resourced European languages must be measured
  rather than assumed.
- More engineering is carried in-house if a self-hosted deployment is chosen.

### Neutral

- The provider abstraction would have been built regardless; this decision only constrains which
  implementations are acceptable.

## Enforcement

**Planned for M2**, the milestone that introduces the first model call. Not yet in place, and this section says
so rather than describing a check that does not exist.

The provider registry will reject any endpoint whose host is not on the allowed list, with a test asserting the
list holds only providers under European law. A second test will assert that the extraction path refuses to
proceed when the identifier detector reports failure, so the failure is closed rather than silent.

## Security impact

Reduces the number of external parties that ever hold candidate content to zero outside the Union.

## Privacy impact

Removes the cross-border transfer entirely. Minimisation and closed-failure pseudonymisation remain as
independent controls.

## Performance impact

To be measured. European inference endpoints are geographically closer to the deployment, which should reduce
latency; model quality per language is the metric that decides adoption.

## Migration / rollback plan

Adopting a new provider means implementing the provider interface and passing the per-language reference set.
Reverting this decision would require a transfer impact assessment and a documented transfer mechanism before
any call is made, which is deliberately a higher bar than a configuration change.

## Links

- General Data Protection Regulation, Chapter V (transfers of personal data to third countries):
  https://eur-lex.europa.eu/eli/reg/2016/679/oj#d1e3462-1-1
- The vendor's own data-residency documentation was read on 2026-09-05 and is cited in the working notes rather
  than here, so this record argues from the regulation rather than from one supplier's page.
