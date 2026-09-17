# ADR-0012: Launch in the Flemish Region first

## Status

Accepted

## Context

Placing people with employers is a regulated activity in Belgium, and the rules are regional. The definitions
are functional, by activity rather than by medium: the Flemish decree defines private placement as the
services of an intermediary assisting workers in finding work and employers in finding workers, and the
German-speaking Community's decree states expressly that it applies whatever the channel of communication.
Being a website is not a category under these texts; it is a channel.

The three regions differ on what must happen before the first service is rendered:

| Region | Prior formality |
|---|---|
| Flanders | None. No authorisation, licence, registration or notification. Conditions of substance apply. |
| Brussels | A prior declaration, registered. |
| Wallonia | A prior formality, whose applicability turns on a question of qualification not yet settled. |

Whether this product's operation falls under the placement definitions, and so under the Brussels and
Walloon formalities, is with a lawyer. ADR-0010 moves the product toward the exclusion for mere publication of
offers and requests, which is the point that duty turns on, but it does not settle it.

Exercising a placement activity without the prior formality is criminally sanctioned in Brussels and in
Wallonia.

## Decision drivers

1. Flanders is the only Belgian region in which the product can open while the qualification question is
   with a lawyer, because it imposes no prior formality.
2. A launch gated on a legal question with no date is a launch with no date.
3. The home market is Belgium, and the plan's "the whole Union from version one" was written before the
   regional texts were read.

## Decision

**The product opens in the Flemish Region first.** Sign-up is limited to that region at launch, and the
restriction is a configuration of open regions, not a condition scattered through the code. Other regions and
other member states open as their own formalities are met, one value each.

Flanders imposes no formality, and it imposes conditions that bind from the first service rendered. Each is a
product requirement:

- **The language legislation.** The Flemish decree binds the intermediary to the language rules without
  restating them. What those rules require of a job offer, a match notification, a screening question and a
  contract has to be established before launch, and the product needs a Dutch interface and a Dutch mediation
  path for the Flemish Region regardless of its coverage of the other twenty three languages. This is open
  work, named below.
- **A code of conduct and quality criteria** set by the Flemish Government apply, and breach of the code is
  criminally sanctioned. Both texts have to be obtained and read against the product before launch.
- **A right of access to one's file** for the client and for the worker, with refusal a criminal offence. A
  recruiter is a client. Where that right meets the blind profile has to be resolved: what a recruiter may ask
  for is the file the product holds about the recruiter, not the profile of a candidate they have not matched
  with, and the product's answer has to be written down and defended.
- **Candidates are never charged**, which the product already guarantees.

## Alternatives considered

### Open in the whole Union from version one

Rejected. It was the plan, and it assumed a uniform regime that the regional texts do not provide. Opening
where a prior formality may be owed, before it is settled whether it is owed, is the risk that carries a
criminal sanction.

### Open in Brussels and Wallonia after filing the formalities as a precaution

Rejected for now. Filing a declaration as a private placement agency concedes the qualification the lawyer
is asked to assess, and it binds the product to the obligations of that status in those regions. It remains
the fallback if the qualification goes that way.

### Wait for the qualification before opening anywhere

Rejected. Flanders needs no answer to that question to open, and a product with users is what produces the
facts the question depends on.

## Consequences

### Positive

- A launch that does not wait on an unanswered question.
- The regional restriction is a mechanism the product needs anyway, since every member state has its own
  formalities.

### Negative

- The launch is smaller than the plan promised, and the twenty four language coverage does not itself buy a
  launch anywhere.
- Three pieces of open work with no date yet: the language regime, the code of conduct and quality criteria,
  and the reading of the right of access against the blind profile.

### Neutral

- Nothing in the code of the product depends on which region opens first, only the configuration.

## Enforcement

**Planned for M4**, the first milestone with sign-up.

A test asserting that a sign-up from a region not in the configuration is refused, with the reason stated in
words. A test asserting that the Dutch interface is complete, refusing a build with a Dutch catalogue that
misses a key present in English. The language regime, once established, becomes a written rule in `docs/`
with the test that holds it, and this record is amended by the one that adopts it.

## Security impact

None.

## Privacy impact

The region a person signs up from is recorded to apply this rule, and is no finer than the region.

## Performance impact

None.

## Migration / rollback plan

Opening another region is adding one value to the configuration once its formality is met. Closing one is
removing it; existing accounts in a closed region keep what they have and can add nothing, which has to be
said to them in words.

## Links

- ADR-0010 (presence in the recruiter deck is opt-in), which moves the product toward the exclusion for mere
  publication
- The regional texts on private placement are cited in the compliance dossier rather than here, because the
  dossier carries their dates and versions
