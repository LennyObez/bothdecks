# ADR-0006: Both sides have a deck, and a pass leaves no browsable trace

## Status

Accepted. Amended by [ADR-0010](0010-presence-in-the-recruiter-deck-is-opt-in.md): a candidate appears in a
recruiter's deck only after enabling it, so the deck holds those who said yes to the offer and those who chose
to be found. The rest of this record stands.

## Context

A naive reading of the swipe model makes the recruiter reactive: candidates express interest, recruiters
respond. That model cannot start. A recruiter who publishes their first offer opens an empty deck, sees
nothing, and leaves before the marketplace has any liquidity. The candidate side has the mirror problem: a new
offer is invisible until someone happens to reach it.

Separately, the product's audience lives with constant rejection. A history of everyone who passed on you, or
everyone you passed on, is a screen that serves no decision and costs the user something every time they open
it.

## Decision drivers

1. Both sides must have something to do on their first session, before any reciprocal interest exists.
2. Interest already expressed by the other party is the most valuable signal in the system and must be acted on
   quickly.
3. The product is used by people in a fragile position; retaining and surfacing rejection is a harm the product
   can simply decline to cause.

## Decision

**Both sides have a deck.** The candidate's deck holds offers matching their profile and preferences. The
recruiter's deck holds profiles matching their offers, whether or not those candidates have shown interest. A
mutual right swipe in either direction creates the match and opens the conversation.

**Interest already expressed is surfaced first.** Offers whose recruiter already swiped right on the candidate
rise in the candidate's deck and are marked as such; candidates who already swiped right on the offer rise in
the recruiter's deck. The boost is bounded: a poor match where the other party said yes is still a poor match,
and an unbounded boost would turn the deck into a queue of mutual mismatches.

**The blind profile becomes structural, not optional.** Because a recruiter is shown profiles that did not ask
for anything, no name, photo or contact detail is rendered before a match. What a recruiter evaluates is
skills, experience, mobility and languages.

**A left swipe leaves no browsable trace.** Not for the person who made it, not for the person who received it.
No route, view or contract field exposes one. Right swipes are retained and browsable with their state
(waiting, matched, expired), and matches are retained as the entry point to conversations.

The internal log required by the ranking model and by regulatory logging records both directions. It is
technical, access-controlled, and never surfaced as a user-facing history of rejection.

## Alternatives considered

### Recruiter responds only to expressed interest

Rejected. It cannot bootstrap either side of the marketplace, and it wastes the recruiter's judgement on a
pre-filtered pool they did not choose.

### Retain and show pass history to its author

Rejected. It offers no decision the user needs to make, and it invites rumination in an audience already prone
to it. The undo affordance covers the only legitimate need, which is correcting the swipe you just made.

### Show the candidate who passed on them

Rejected outright. It is the most direct harm the product could inflict on its primary user.

## Consequences

### Positive

- Both sides have a usable deck from the first session.
- A mutual match can be one gesture away rather than two rounds away.
- The product carries no screen whose purpose is to display rejection.

### Negative

- Recruiters see profiles of people who have not opted into being seen by them, which raises the bar on the
  blind profile and on the transparency the product owes candidates about being discoverable.
- The internal log and the user-facing history diverge, so two representations must be kept correct.

### Neutral

- The undo affordance is the only route back to a swipe, and it covers one action.

## Enforcement

**Planned for M4**, the milestone that builds the decks. Not yet in place.

A test will assert that no route, view template or contract field exposes a left swipe. A second will assert
that no contact detail or photo appears in the serialised profile a recruiter receives before a match. A third
will assert that the reciprocal-interest boost stays within its configured maximum.

## Security impact

None.

## Privacy impact

Significant and deliberate. Proactive discovery means a candidate is visible to recruiters they have not
contacted, which must be disclosed plainly at sign-up and controllable in settings. The blind profile is the
mitigation that makes it proportionate.

## Performance impact

The reciprocal-interest boost is a lookup against an indexed table of expressed interest, applied during
ranking.

## Migration / rollback plan

The boost weight is configuration. Disabling proactive recruiter discovery would mean filtering the recruiter
deck to expressed interest only, which is a query change, but it would remove the marketplace's ability to
start.

## Links

- ADR-0005 (codes and bubbles, which determine deck contents)
