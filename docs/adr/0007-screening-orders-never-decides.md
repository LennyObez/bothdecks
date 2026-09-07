# ADR-0007: Automated screening produces an order of treatment, never a decision

## Status

Accepted

## Context

A recruiter with one open role and forty matches has a real volume problem. The obvious answer is to let them
automate the first exchange: an opening message, a few closed questions, and a rule that sorts the answers.

The obvious answer is also how automated rejection enters a hiring product. Once a rule can route an answer, the
shortest path is a rule that routes it to "declined", and the product becomes a system that rejects people
without a human ever reading their file.

Recruitment is treated as a high-risk application under European rules on artificial intelligence, and a score
that a third party relies on heavily has been held to be a decision in its own right in data-protection case
law. Beyond the legal exposure, a product whose users discover it auto-rejects them has lost them.

## Decision drivers

1. The recruiter's volume problem is real and must be solved, not refused.
2. A rule engine that can express rejection will eventually be used to express rejection.
3. The safest control is one the interface makes impossible to configure, not one the policy forbids.

## Decision

A recruiter may compose a screening sequence: an opening message sent when the match opens, and multiple-choice
questions the candidate answers in one tap (availability, expected salary, mobility, work authorisation, level
on a named skill).

Answers route the conversation into one of three states: **priority**, **standby**, or **filed**. That is the
complete set. There is no rejection state, no auto-close, no auto-archive that hides the conversation from the
recruiter's own view. Filing changes the order in which the recruiter works, nothing else.

Three rules bound the mechanism:

- The candidate is told plainly that this part is automated, before answering.
- The candidate can write freely at any point instead of choosing an option.
- No answer, and no combination of answers, ends the conversation or communicates a refusal.

The constraint is enforced by the domain type rather than by policy: the routing outcome is a closed set of
three values with no rejection member, so a rejection cannot be expressed even by a caller that wants to.

## Alternatives considered

### A general rule engine with a documented prohibition on auto-rejection

Rejected. A prohibition written in documentation is not a control. The type system can make the state
unrepresentable, and where it can, it should.

### No automation at all

Rejected. It leaves the recruiter's volume problem unsolved, and an unsolved volume problem is answered outside
the product by ignoring candidates, which is worse for candidates than a structured question they answer in
one tap.

### Automation that can decline, with a human confirmation step

Rejected. A confirmation step in front of a pre-computed decision is a rubber stamp. The literature on
structured evaluation is consistent that the decision must be made against criteria, not ratified after the
fact.

## Consequences

### Positive

- The recruiter's volume problem is addressed without the product ever declining a candidate.
- The guarantee is structural, so it survives future contributors who did not read this document.
- Candidates get a fast, low-effort way to convey the facts recruiters actually need.

### Negative

- Recruiters who want auto-rejection will find the product does not offer it. That is the intended outcome, and
  it is a sales conversation the product should be willing to lose.
- Conversations accumulate in the filed state and need their own review affordance, since nothing removes them.

### Neutral

- The screening sequence is optional; a recruiter can write every first message by hand.

## Enforcement

**Planned for M4**, the milestone that builds conversations. Not yet in place.

A test will assert that the routing outcome type has exactly three members and that none terminates a
conversation. A second will assert that no code path moves a conversation to a closed state because of a
screening answer. A third will assert that the candidate-facing view renders the automation disclosure whenever
a sequence is active.

## Security impact

None.

## Privacy impact

Screening answers are personal data collected for a stated purpose and retained under the same policy as the
conversation they belong to. They are structured rather than free text, which limits incidental collection.

## Performance impact

None. Routing is evaluated once per answer.

## Migration / rollback plan

Sequences are per requisition and can be disabled without affecting existing conversations. Adding a rejection
outcome would require changing a closed type and removing three tests, which is the intended level of friction.

## Links

- ADR-0006 (reciprocal decks)
