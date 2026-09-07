# ADR-0001: A single repository for the server, the web, both mobile applications and the contract

## Status

Accepted

## Context

The product is one backend serving three clients: a server-rendered website, an Android application and an iOS
application. They communicate through one OpenAPI contract. The server is PHP, the shared mobile module is
Kotlin, the iOS layer is Swift, the infrastructure is declarative configuration.

Splitting a polyglot product across repositories is the common reflex. It is also how a contract silently
drifts: the server ships a field rename, the mobile clients follow days later, and nothing in either repository
fails at the moment the mistake is made.

The release cadences genuinely differ (the web ships continuously, mobile ships in store review trains), and
macOS runners cost more than Linux ones, so a naive single repository would rebuild everything on every change.

## Decision drivers

1. Contract drift between one server and three clients is the largest correctness risk in this product.
2. A change to the contract must be reviewable as one diff, not three.
3. Continuous-integration cost is a solvable problem; a silently broken client is not.

## Decision

One repository holds the server, the website, both applications, the shared module, the contract, the design
tokens, the taxonomy pipeline, the infrastructure and the documentation.

Path filters scope each workflow: the iOS pipeline runs only when `apps/ios/**`, `shared/**` or `contract/**`
changes; the Android pipeline only when `apps/android/**`, `shared/**` or `contract/**` changes; the server
pipeline only when `apps/server/**` or `contract/**` changes. Tags are prefixed per artefact so release trains
stay independent.

The framework the server is built on stays in its own repository and is consumed as a pinned dependency.

## Alternatives considered

### One repository per artefact

Rejected. It makes the contract change a coordination problem across four pull requests, with no mechanism that
fails at the moment of divergence. The cost lands exactly where the risk is highest.

### Server and web together, mobile separately

Rejected for the same reason in weaker form: the contract still spans two repositories, and the shared module
would have to be published as an artefact before either application could consume a change.

## Consequences

### Positive

- A contract change, its server implementation and its three client adaptations are one reviewable commit.
- One issue tracker, one project board, one set of labels, one release history.
- A reader of the repository sees the whole system rather than a fragment of it.

### Negative

- Workflow configuration is more involved: every pipeline needs correct path filters, and a filter that is too
  narrow silently skips a build that should have run.
- Repository size grows faster than a single-language project would.

### Neutral

- Contributors clone more than they need. For a product of this size that cost is negligible.

## Enforcement

A workflow discovers the directories from what git tracks (never from a list written in the workflow, which
could not detect the very thing the check exists to catch) and fails when one holds source that no pipeline's
path filter builds. Documentation-only directories are exempt, because the repository-wide jobs that carry no
path filter already cover them; the exemption ends the moment source lands in one.

## Security impact

None. Secret scoping is handled by deployment environments rather than by repository boundaries, which is the
stronger mechanism in either layout.

## Privacy impact

None.

## Performance impact

None on the product. Continuous-integration wall time is controlled by path filters and caching.

## Migration / rollback plan

Splitting later is mechanical: each top-level directory is already self-contained, and history can be extracted
per path. The contract tests would have to be replaced by published-artefact version checks.

## Links

- ADR-0002 (framework choice)
