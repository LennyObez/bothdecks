# Architecture decision records

Each record states a decision, the forces that produced it, what was rejected and why, and the check that makes
the decision hold. A decision no check enforces is a preference, and the record says so.

Records are immutable once accepted. A decision that changes gets a new record that supersedes the old one; the
old one stays, marked superseded, because the reasoning that was true at the time is part of the history.

| Record | Decision |
|---|---|
| [0001](0001-single-repository.md) | A single repository for the server, the web, both mobile applications and the contract |
| [0002](0002-pulsar-as-backend-and-web-renderer.md) | Pulsar as both the backend and the web renderer |
| [0003](0003-eu-only-model-inference.md) | Model inference runs only on infrastructure under European law |
| [0004](0004-the-product-name-lives-in-one-place.md) | The project name is a codename, and the product name is a single configuration value |
| [0005](0005-codes-not-strings.md) | Occupations and skills are codes; text is only a rendering |
| [0006](0006-reciprocal-decks-and-asymmetric-history.md) | Both sides have a deck, and a pass leaves no browsable trace |
| [0007](0007-screening-orders-never-decides.md) | Automated screening produces an order of treatment, never a decision |

## Writing a new one

Copy [`0000-template.md`](0000-template.md), take the next number, and fill every section. The sections that
are most often skipped are the ones that matter most later: *Alternatives considered* is what stops the same
debate being reopened, and *Enforcement* is what separates a decision from an intention.

An architecture change that touches a module boundary, a data flow crossing a trust boundary, a dependency the
product cannot easily leave, or a user-visible guarantee requires a record before the code.
