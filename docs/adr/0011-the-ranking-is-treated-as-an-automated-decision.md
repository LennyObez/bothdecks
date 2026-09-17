# ADR-0011: The ranking is treated as an automated decision from the first version

## Status

Accepted

## Context

Every deck is an order. The order comes from a score, and a recruiter reading a deck reads its top before its
bottom. Article 22 of the General Data Protection Regulation gives a person the right not to be subject to a
decision based solely on automated processing that produces legal effects or similarly significant ones, and
where such a decision is lawful it requires human intervention, the right to express a point of view, and the
right to contest.

Whether a deck rank is such a decision is a question of fact about this product. In C-634/21 the Court of
Justice held that a score on which a third party draws strongly is itself the decision, even though a human
signs what follows. Whether recruiters draw on rank that strongly cannot be known before there are recruiters,
and by then the obligation, if it applies, has applied from the first card.

## Decision drivers

1. Assuming the article applies is additive work that is never undone. Assuming it does not is a permanent
   obligation to measure, in production, that recruiters do not follow rank, against a threshold fixed in
   advance and defended forever.
2. The product's own promise, that no one is refused by a machine, is the same promise in different words.
3. The ranking decision log is built for the learned ranking of M9 regardless, and it is the only artefact that
   will ever answer the question of fact.

## Decision

**The order of every deck is treated as a decision under article 22, from the first version**, and the three
rights it carries are built rather than argued away.

- **Human intervention.** Every decision that follows a rank is taken by a named person, recorded with their
  name and the time. The screening sequence orders conversations and cannot close one (ADR-0007). A lane on
  the board is a lane, and moving somebody into it is an action with a name against it.
- **A point of view.** A candidate can see why a card was ordered as it was, in words derived from the scoring
  features, and can add to their profile in answer. A recruiter sees the same explanation for the same card.
- **Contestation.** A candidate can contest the order they were given, and the contest reaches a person who
  answers in writing. The contest, its answer and the rank at the time are kept together.

The ranking decision log records, for every card shown: the two parties, the score, the features that produced
it, the algorithm version, the explanation rendered, the position in the deck, and the times of display and of
decision. It exists from the first deck.

## Alternatives considered

### Treat the rank as a mere ordering and measure whether recruiters follow it

Rejected. The measurement has to run in production, forever, against a threshold fixed in advance, and the
day it fails the obligation has already applied to every deck before it.

### Remove the score and show decks in arrival order

Rejected. A deck without a score is a deck a person cannot get through, and the product's value is the order.

## Consequences

### Positive

- The three rights are product features with screens, not clauses in a notice.
- The log that answers the question of fact exists from day one and is the training set for M9 as well.

### Negative

- Contestation is a workload with a person behind it, and it has to be staffed before it is promised.
- Every scoring feature has to be explainable in words, which constrains what M9 may add.

### Neutral

- The article's applicability remains a question of fact. The product is built so the answer does not matter.

## Enforcement

**Planned for M4**, with the first deck, and extended in M9.

A test asserting that every displayed card has a log line, with a fixture that renders a deck and counts. A
test asserting that every log line carries an explanation derived from its own features, refusing a line
whose explanation names a feature absent from the score. A test asserting that the routing outcome type of the
screening sequence cannot express a rejection (ADR-0007). In M9, a test that every feature admitted to the
learned model has a rendering in words.

## Security impact

None.

## Privacy impact

The decision log is personal data about both parties and is retained for as long as a contest can be raised,
then purged. Its access is restricted to the people who answer contests and to the audit function.

## Performance impact

One write per displayed card, on the path that already writes the display event.

## Migration / rollback plan

There is nothing to roll back; a product that has these rights can drop none of them without a re-assessment.
Adopting a new ranking model means passing the explainability test before it serves a deck.

## Links

- General Data Protection Regulation, article 22:
  https://eur-lex.europa.eu/eli/reg/2016/679/oj#d1e2838-1-1
- Court of Justice of the European Union, C-634/21, judgment of 7 December 2023
- ADR-0007 (screening orders, never decides)
- ADR-0010 (presence in the recruiter deck is opt-in)
