# Security policy

## Reporting a vulnerability

Report privately through GitHub's private vulnerability reporting on this repository:
**Security → Report a vulnerability**. That channel is monitored and keeps the report confidential until a fix
is available.

Please do not open a public issue for a security problem, and please do not disclose it publicly before a fix
has shipped.

A useful report contains what an engineer needs to reproduce the problem: the affected component, the steps or
request that triggers it, what you observed, and what you expected. A proof of concept helps; a working exploit
is not required and is not expected.

## What to expect

| Stage | Timing |
|---|---|
| Acknowledgement that the report was received | Within 3 working days |
| Initial assessment, with a severity and a direction | Within 10 working days |
| Fix for a critical or high-severity issue | Prioritised over feature work |
| Coordinated disclosure | Agreed with the reporter before publication |

Reporters who wish to be credited are credited in the release notes.

## Scope

In scope: this repository's server, web, Android and iOS code, its contract, its infrastructure definitions,
and the deployed service.

Out of scope: findings that require physical access to a user's unlocked device; reports produced by an
automated scanner with no demonstrated impact; denial of service through volume alone; and vulnerabilities in
third-party dependencies that already have a public advisory, which are handled by the dependency update
process rather than by this channel.

## What the product will protect

The controls below are the product's security commitments. **They are not all implemented yet**, and this
document says which are, because a security policy that overstates its protections is itself a risk.

Each becomes a test in the milestone that adds the behaviour it protects; the README tracks that mapping.

- Candidate contact details and photos are not rendered to a recruiter before a mutual match.
- Fields that invite discrimination are never written to the candidate record, even when present in an
  uploaded document.
- No candidate identity leaves for an external system without a recorded, dated, revocable consent.
- Model inference runs only on infrastructure under European law, and the request path fails closed if the
  identifier-removal step does not complete.
- Uploaded documents are scanned and sanitised before any processing.
- Audit entries are chained so that tampering is detectable.

Implemented today, each enforced by a test in the `guarantees` suite:

- The framework dependency is pinned to a single commit, so the foundation cannot move without a decision that
  leaves a diff.
- Every workflow action is pinned to a commit, so a moved tag cannot substitute code into a pipeline that
  holds a write token.
- The declared PHP extensions match what the dependencies require, so a pipeline cannot fail at install and
  report nothing about the code.
- The application's wiring is exercised by an integration test that dispatches a real request, rather than
  assumed.
- Error display is decided by the front controller from the process environment, so a permissive `php.ini`
  cannot turn a failure into a page carrying paths and a stack trace.

## Dependencies

Dependencies are updated automatically, and the update is gated by the same test sequence as any other change.
The framework this product is built on is pinned to an exact commit; a nightly pipeline reports divergence from
its active branch without blocking merges.
