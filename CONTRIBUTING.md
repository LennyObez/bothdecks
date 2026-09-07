# Contributing

## Before writing code

Read [`docs/architecture.md`](docs/architecture.md) for the module layout and
[`docs/testing.md`](docs/testing.md) for the test method. A change that touches a module boundary, a data flow
crossing a trust boundary, a dependency the product cannot easily leave, or a user-visible guarantee needs an
architecture decision record before the code.

## Workflow

1. Branch from `main`: `feat/`, `fix/`, `perf/`, `refactor/`, `docs/`, `test/`, `chore/`, `security/`.
2. Write the failing test first, and run it in the failing state.
3. Implement the smallest change that makes it pass.
4. Run the full gate sequence from [`docs/testing.md`](docs/testing.md): the whole sequence, not a scoped run.
5. Open a pull request describing what changed and what proves it.

## Commits

Conventional Commits, scoped by module:

```
feat(taxonomy): resolve alternative labels across locales
fix(swipe): stop a passed card reappearing after an undo
security(profile): reject uploads whose declared type differs from their content
```

Types: `feat`, `fix`, `perf`, `refactor`, `docs`, `test`, `chore`, `security`.
Scopes: the module names in `docs/architecture.md`, plus `contract`, `design`, `infra`, `ci`.

**Every commit is signed**: `git commit -S`. If signing is unavailable, do not commit until it is.

**A milestone produces one commit.** The pull requests that led to it stay as the review record; the commit is
the delivery. That keeps the history readable at the scale someone actually reads it.

## What never enters a tracked file

Every tracked file is published. The following belong in local notes, which are untracked:

- A named past defect, or a count of what was broken.
- The development environment: a machine, a personal path, which host runs what.
- Anything that reads as denigrating the product.

Write what the code **guarantees** and why the property matters. The reasoning survives; the incident does not
belong in the repository.

## Pull requests

Keep them small enough to review in one sitting. A pull request that changes the contract must change the
server, the frozen specification and every affected client in the same diff. That is the reason this is one
repository.

The description states what changed, what proves it, and which decision record it implements or amends.

## Review checklist

- Does a test fail without this change?
- Is the test non-tautological? Does it assert observable behaviour rather than the steps taken?
- Does anything here silence a finding rather than fix its cause?
- Does a user-visible string exist outside a translation catalogue?
- Does this add a field that invites discrimination?
- Does this expose a left swipe, a contact detail before a match, or a way to reject automatically?

## Conduct

[`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md) applies here, to the maintainer on the same terms as to everyone
else.

## Reporting a security problem

Not through an issue or a pull request. See [`SECURITY.md`](SECURITY.md).

## Licensing a contribution

Clause 4 of [`LICENSE`](LICENSE) covers it: what you submit is offered under the same terms, and submitting it
confirms you hold the right to do so. There is no separate agreement to sign, and no trailer to add.
