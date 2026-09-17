# ADR-0010: Presence in the recruiter deck is opt-in, off by default

## Status

Accepted. Amends [ADR-0006](0006-reciprocal-decks-and-asymmetric-history.md), whose reading that every
candidate populates the recruiter deck no longer holds. Everything else in that record stands.

## Context

ADR-0006 gives the recruiter a deck of profiles matched to their offers, whether or not those candidates have
shown interest, and makes the blind profile the safeguard that keeps that proportionate. The record named the
privacy impact and said discoverability had to be disclosed at sign-up and controllable in settings; it did
not say which way the control pointed by default.

Two rounds of research on primary sources answered that question in the only way that survives refutation.
No authority anywhere has ruled on the exact operation of showing an employer the profile of a person who
never contacted them. The two research topics that attacked it reached opposite conclusions, consent on one
side and legitimate interest on the other, and both rested on citations their refuters took apart. Separately,
placing candidates in front of employers is what the regional Belgian texts on private placement describe,
and exercising that activity without the prior formality is criminally sanctioned in two of the three regions;
the Brussels exclusion for the mere publication of offers and requests is the point on which the duty turns,
and a ranked deck of people who asked for nothing sits outside it. A leading application store's rule against
presenting real people as objects to be sorted was named as a third convergent risk.

## Decision drivers

1. Consent dissolves the legal question rather than defending it: an operation a person asked for is not the
   operation nobody has ruled on.
2. Only one of the two starting positions can be left. Widening from opt-in to on-by-default is a product
   change decided in a morning; narrowing the other way is a re-consent campaign across the whole base.
3. The product's audience is exposed to rejection. Being seen by employers one never approached is something
   a person should choose, not discover.

## Decision

**A candidate appears in a recruiter's deck only after enabling it.** The control is off when the account is
created. The lawful basis is consent: explicit, recorded with the date and the wording it was given under,
and revocable from the same screen that granted it.

What the recruiter's deck holds at any moment is therefore two kinds of people: those who said yes to that
recruiter's offer, who appear first and are marked as such, and those who chose to be found. Nobody else.

The prompt that asks for consent is placed where a candidate has a reason to say yes, after the first deck
session rather than at sign-up, with the blind profile shown beside the question so the person sees exactly
what an employer would see. Declining changes nothing else about the product.

The recruiter deck ships in M4 with the candidate deck, as originally planned. It is thinner at launch than
ADR-0006 assumed, and the milestone's demonstration has to account for a deck that fills over time rather
than one that is full on day one.

## Alternatives considered

### On by default, with the setting to turn it off

Rejected. It is the position whose legal basis two research rounds could not establish, and it is the
position that cannot be left without re-consenting every existing user.

### No recruiter deck at all until the question is settled

Rejected. The recruiter deck is what the paying side buys, and the question is settled by consent rather than
by waiting for an authority that has not spoken.

### Consent asked at sign-up

Rejected. At sign-up a person has seen nothing and has no reason to say yes; a consent given there is a
checkbox, not a decision. Asked after a session, beside the profile an employer would see, it is informed.

## Consequences

### Positive

- The operation with no established basis becomes an operation each person asked for.
- The product moves toward the exclusion for mere publication, which is the point the placement formality
  turns on, without resting on it.

### Negative

- The recruiter deck is thin at launch, and the subscription has to be sold on the offer side first.
- The consent wording is versioned data, and a change to it is a re-consent for everyone under the old text.

### Neutral

- The blind profile is unchanged. Consent decides whether a person is shown; the blind profile decides what.

## Enforcement

**Planned for M4**, the milestone that builds the decks.

Three tests. The schema default for the flag is off, asserted directly. A profile whose flag is off never
appears in the query that builds any recruiter deck, asserted with a fixture of profiles that match an offer
perfectly and have the flag off. Every enabled flag carries the timestamp and the wording version it was
given under, and a row without them is refused by the database.

## Security impact

None.

## Privacy impact

Reduces the impact ADR-0006 recorded. Discoverability moves from a disclosed default to a recorded choice,
and the record of that choice is itself personal data, kept with the consent it evidences.

## Performance impact

One more predicate in the deck query, on an indexed column.

## Migration / rollback plan

Nothing to migrate: no user exists before this record. Reversing it would be the re-consent campaign this
record exists to avoid, which is deliberately a high bar.

## Links

- ADR-0006 (both sides have a deck; amended here)
- ADR-0011 (the ranking treated as an automated decision), which governs the order of the deck this record
  governs the membership of
