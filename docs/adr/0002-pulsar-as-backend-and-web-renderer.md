# ADR-0002: Pulsar as both the backend and the web renderer

## Status

Accepted

## Context

The product needs a JSON API for three clients, a server-rendered website with app-like interactive surfaces, a
real-time layer for conversations and match notifications, background jobs, structured extraction from
documents, vector retrieval, PDF generation, and interface text in the 24 official languages of the European
Union.

The Pulsar Framework, an HMVC framework for PHP 8.5, already ships the majority of that: server-driven reactive
components for interactivity without a single-page application, static rendering with incremental
regeneration for indexable pages, WebSocket with private and presence channels, a provider abstraction for
model inference with structured-output and embedding pipelines, a PostgreSQL vector store, OpenAPI generation
with backward-compatibility detection, consent, retention, purge and subject-access machinery, queues with
retry and dead-letter handling, S3-compatible storage, push, mail and SMS channels, persistent worker runtimes,
and catalogues for the 24 languages plus Luxembourgish.

It is at release-candidate stage. Its stable-marked surface is committed; the rest may change before the first
general release.

## Decision drivers

1. The parts of this product that are hard are the domain: taxonomy, matching, extraction. Everything else is
   plumbing that already exists.
2. A framework whose author is also this product's author means defects are fixable rather than reportable.
3. Building the product exercises the framework on a real workload, which is where design mistakes surface.

## Decision

Pulsar is the backend and the web renderer. The dependency is pinned to an exact commit, never a range.

A nightly pipeline replays the product's suite against the head of the framework's active branch. It has no
authority to block a merge; it exists so that a breaking change is discovered the day it lands rather than at
the next upgrade.

Every framework extension the product relies on must first pass a verification step: read its code, run its
test slice, and record the result. A manifest description states the author's intention and is not evidence
that the code behaves as described.

## Alternatives considered

### A mainstream framework in another language

Rejected on the balance of what it would buy. A larger ecosystem and a dedicated security team are real
advantages, but the product would then need the interactivity layer, the residency-constrained inference
abstraction, the consent and retention machinery and the 24 language catalogues built or assembled from parts,
which is most of what is already present here.

### A separate service for real time and inference

Rejected for a single operator. Each additional deployable is an additional thing to secure, monitor, upgrade
and be woken up by.

## Consequences

### Positive

- The plumbing for a regulated domain is present rather than pending.
- A framework defect is fixable in the same session it is found.

### Negative

- Two products are maintained by the same person; framework security work competes with product work. This is
  budgeted rather than hoped away.
- The ecosystem for document extraction, video processing and machine learning is thinner than in other
  language communities. Those needs are met by in-house code or by services.
- Release-candidate status means the non-stable surface can move. Pinning to a commit contains this.

### Neutral

- The product's public web surface is server-rendered. This suits indexable offer pages and removes an entire
  client-side build from the critical path.

## Enforcement

The dependency is pinned to a commit; a test asserts that the resolved version matches the lock file and that
no version range is declared. The nightly compatibility pipeline reports drift without blocking.

## Security impact

The product inherits the framework's security posture, including its audit chain and its self-hosted
challenge mechanism. It also inherits responsibility: a framework vulnerability is this product's vulnerability.

## Privacy impact

The framework's consent, retention, purge and subject-access components are used rather than reimplemented,
which reduces the surface where a privacy control could be written incorrectly.

## Performance impact

A persistent worker runtime is used rather than per-request process startup, which is what makes long-lived
connections and warm caches possible.

## Migration / rollback plan

The domain modules depend on framework interfaces rather than concrete classes wherever the framework offers
one. Replacing the framework would mean rewriting the adapters and the templates, not the domain.

## Links

- ADR-0001 (single repository)
- ADR-0003 (model inference residency)
