# ADR-0009: Hosting runs in a hyperscaler's Paris region, and the operator's jurisdiction is an accepted residual risk

## Status

Accepted. Supersedes [ADR-0008](0008-staging-runs-on-existing-infrastructure.md). Leaves
[ADR-0003](0003-eu-only-model-inference.md) in force for model inference, which is a different operation from
hosting and is decided there.

## Context

The product holds personal data of people looking for work: profiles, uploaded documents, conversations, and a
log of which cards were shown to whom. Where that data lives, and under whose law, is the first question a
compliance dossier asks.

The earlier position was that every provider in the stack, compute included, had to be a company under
European law. That position was stricter than the regulation it cited. The General Data Protection Regulation
applies by the controller's establishment and by the people concerned, not by the geography of a disk; it
binds this product wherever the servers stand. Its Chapter V governs transfers to third countries, and data
that rests in France on a processor bound by a data processing agreement and standard contractual clauses is
not transferred anywhere in that chapter's sense.

What the earlier position was reaching for, without naming it, is the jurisdiction of the operator. A company
under United States law can be ordered to produce data it holds, including data stored in Paris. That is a
residual risk to be stated, weighed and mitigated. It is not a prohibition, and the rule that excluded every
such operator was a positioning choice presented as an obligation.

The owner already operates a virtual machine in the Paris region of a hyperscaler, with a control panel, TLS
and a process manager in place.

## Decision drivers

1. A compliance argument should say what is true. A rule stricter than the law is legitimate only when it is
   written as a choice, so it can be weighed against its cost.
2. A deployment target that exists is worth more than a better one deferred; the foundation milestone ends on
   a deployment and cannot end without a host.
3. The residual risk is real and has known mitigations, each of which can be checked rather than promised.

## Decision

**Staging and production both run in the Paris region of a hyperscaler.** Personal data rests in the European
Union. The operator's jurisdiction is accepted as a residual risk and is mitigated, not ignored:

1. **Encryption at rest with keys the owner holds.** Data on the disk is unreadable to anyone who obtains the
   disk, the operator included, without a key that is not stored with it.
2. **A signed data processing agreement and standard contractual clauses**, kept in the compliance dossier
   with their dates.
3. **Staging holds no real data.** Its database is built from a generator committed to this repository, so
   what it holds is fabricated by construction. Its credentials, keys and provider accounts are its own, and it
   is reachable only behind authentication.
4. **The region is written in one place**, in `infra/`, so moving is a change of one value and a diff rather
   than a search.

**Model inference is not covered by this record.** Sending a CV to a model provider is a different operation
from storing it on a disk: the provider reads the content. ADR-0003 stands, and the reason it stands is
unchanged, since the leading provider offers no inference geography inside the Union.

The stricter rule, providers under European law for every layer, is kept as a stated preference for the day
a European provider matches the hyperscaler on what the product needs. It is a preference, and this record
says so.

## Alternatives considered

### Providers under European law for every layer, from the first deployment

Rejected for now. No European provider had been compared on the product's needs, and the milestone would have
waited on that comparison to protect records that did not yet exist. The comparison remains open work.

### Staging on the hyperscaler and production elsewhere

Rejected. It was the position of ADR-0008, and it rested on the reading of the regulation that this record
corrects. Two provider stacks for one product doubles what has to be understood and defended, and the
distinction it drew, an exception for staging only, was an exception to a rule that did not bind production
either.

### A developer machine as the only environment

Rejected. The point of an environment is that it is a real server, reached over a real network, with real TLS
and a real process manager. A laptop proves the code runs on a laptop.

## Consequences

### Positive

- One provider, one region, one set of definitions to understand.
- The compliance argument is stated in the terms the regulation uses, so it can be defended in them.
- The foundation milestone has a deployment target and can close.

### Negative

- The residual risk exists and has to be disclosed in the privacy notice in plain words.
- The stated preference for European providers has to be revisited on purpose, or it will be forgotten.

### Neutral

- The production hostname is pointed at nothing until the first milestone that holds a user, so nothing
  personal is at stake before the mitigations above are in place.

## Enforcement

Two checks exist from this record, and two are planned.

In place: the deployment runs from a script committed in `infra/staging/`, so what happens on the host is
reviewed as a diff rather than typed into a panel; and the staging host answers only behind authentication,
which the deployment script verifies before it reports success.

**Planned for M1**, with the first database: a test asserting that the staging database accepts writes only
from the synthetic data generator, and a check that the key encrypting data at rest is not stored on the
same host as the data.

## Security impact

The host is an internet-reachable surface running the product's code. Staging and production share nothing:
separate credentials, separate keys, no network path between them.

## Privacy impact

Personal data rests in the Union under a data processing agreement. The operator's jurisdiction is a
disclosed residual risk, mitigated by encryption at rest with owner-held keys. Nothing personal reaches staging.

## Performance impact

None to measure yet. The region is the one closest to the home market.

## Migration / rollback plan

Moving to another provider or region is a change of the values in `infra/` and a redeployment. Reverting to the
stricter rule would mean choosing a European provider first, which is the comparison this record leaves open.

## Links

- General Data Protection Regulation, Chapter V (transfers of personal data to third countries):
  https://eur-lex.europa.eu/eli/reg/2016/679/oj#d1e3462-1-1
- ADR-0003 (model inference stays on infrastructure under European law)
- ADR-0008 (superseded by this record)
