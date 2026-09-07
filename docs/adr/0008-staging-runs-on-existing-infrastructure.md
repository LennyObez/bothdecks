# ADR-0008: Staging runs on existing infrastructure, and the sovereignty rule binds production

## Status

Accepted

## Context

ADR-0003 commits the product to providers under European law for compute, storage, content delivery, mail and
model inference. The reason is the General Data Protection Regulation: candidate profiles, uploaded documents
and conversations are personal data, and a provider subject to a foreign disclosure regime turns every request
into a transfer question.

Staging is a different animal. It exists to prove that a change works on a real server before it reaches
anyone, and it is populated with synthetic data, never with an extract of production. Nothing in it is personal
data, so nothing in it raises a transfer question.

The owner already operates a virtual machine at a hyperscaler, in its Paris region. Data therefore rests in
France; what remains outside European law is the corporate jurisdiction of the operator, not the location of
the disk. Standing up a second environment at a European provider, purely to host fabricated records, would
cost money and operating attention while protecting nothing.

## Decision drivers

1. The sovereignty rule exists to protect personal data. Where there is no personal data, it protects nothing.
2. A staging environment that does not exist proves nothing; one that exists today is worth more than a better
   one deferred.
3. A rule applied where it has no purpose teaches everyone to apply it by reflex, which is how it eventually
   gets applied wrongly in the direction that matters.

## Decision

**Staging runs on the owner's existing virtual machine at a hyperscaler.** Production does not: ADR-0003 stands
unchanged for every environment that holds real data.

Three conditions make this sound, and they are conditions rather than intentions:

1. **Staging never receives production data.** Not a dump, not an anonymised extract, not a single record. Its
   database is built from a generator committed to this repository, so what it holds is fabricated by
   construction rather than sanitised after the fact.
2. **Staging holds no production secret.** It has its own credentials, its own signing keys and its own
   provider accounts, so a compromise there reaches nothing else.
3. **Staging is not reachable by the public.** It carries no index, and it is not a soft route into anything.

If any of the three stops holding, the environment is no longer staging in the sense this record uses, and it
falls back under ADR-0003.

## Alternatives considered

### Apply the sovereignty rule to every environment

Rejected. It would delay having any deployment target at all until a provider comparison that has not been
done, and it would spend money protecting records that were invented for the purpose.

### Run staging on a developer machine

Rejected. The point of a staging environment is that it is a real server, reached over a real network, with
real TLS and a real process manager. A laptop proves the code runs on a laptop.

## Consequences

### Positive

- A deployment target exists now, so the last outstanding item of the foundation milestone can close.
- The sovereignty rule keeps its meaning, because it is stated where it applies rather than everywhere.

### Negative

- Two provider stacks have to be understood rather than one, and the deployment definitions must not quietly
  assume the staging provider's conveniences.
- The distinction has to be defended every time someone proposes to "just test with real data", which is the
  request this record exists to refuse.

### Neutral

- The production provider remains an open question, researched separately.

## Enforcement

**Planned for M1**, the milestone that introduces a database.

A test will assert that the data generator is the only writer the staging environment accepts, and the
deployment definition will carry no credential shared with production. Until a database exists there is
nothing to enforce, and saying so is better than a check that passes because it has nothing to inspect.

## Security impact

Staging becomes an internet-reachable surface running the product's code. It is treated as untrusted from
production's point of view: separate credentials, separate keys, no network path inward.

## Privacy impact

None, and that is the whole argument. If staging ever holds personal data, this record no longer covers it.

## Performance impact

None.

## Migration / rollback plan

Moving staging to a European provider later is a change of deployment target, not of code. The definitions live
in `infra/` and name the provider in one place.

## Links

- ADR-0003 (European-only model inference), whose scope this record narrows to production
