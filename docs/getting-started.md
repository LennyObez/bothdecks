# Getting started

## Requirements

| Tool | Version |
|---|---|
| PHP | 8.5.1 or later, with `ctype`, `curl`, `dom`, `exif`, `fileinfo`, `gd`, `intl`, `libxml`, `mbstring`, `openssl`, `pdo`, `pdo_pgsql`, `simplexml`, `sodium`, `zip`, `zlib` |
| Composer | 2.10 or later |

**No database is needed at this milestone.** The `pdo_pgsql` extension is declared because the data layer
arrives in M1 and a contributor who installs today should not have to reinstall then; nothing currently reads
from a database. PostgreSQL itself becomes a requirement with the taxonomy.

**A coverage driver is needed for one step only.** Mutation testing needs `xdebug` or `pcov`; every other gate
runs without one. The pipeline installs xdebug.

## Server

```bash
git clone https://github.com/LennyObez/bothdecks.git
cd bothdecks/apps/server
composer install
```

The framework is pinned to an exact commit rather than a version range, so `composer install` resolves the same
code on every machine. Confirm it:

```bash
php -r '$l = json_decode(file_get_contents("composer.lock"), true);
foreach ($l["packages"] as $p) {
    if ($p["name"] === "pulsar/framework") { echo $p["source"]["reference"], PHP_EOL; }
}'
```

Serve the application:

```bash
php -S localhost:8080 -t public
```

`GET /health` reports that the application booted and can read its own identity. It deliberately checks no
dependency: a liveness probe that fails because a downstream service is slow causes the restart loop it was
meant to prevent.

## Configuration

`config/identity.php` holds the application identity. The product name lives there and nowhere else. See
[ADR-0004](adr/0004-the-product-name-lives-in-one-place.md). Environment-specific values are read from the environment:

| Variable | Purpose | Default |
|---|---|---|
| `APP_ENV` | Active environment | `local` |
| `APP_URL` | Canonical public origin, used to build absolute URLs | `http://localhost:8080` |

## Running the gates

Run the whole sequence, not a subset. A scoped run answers a different question, and its green is not the
gate's green.

```bash
composer run cs:check    # formatting
composer run phpstan     # static analysis at max level, no suppressions
composer run test        # every suite
```

Or the three together:

```bash
composer run qa
```

A single suite can be run **while working on it**, to shorten the loop:

```bash
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite guarantees
```

That is not a substitute for the gate. A scoped run answers a different question, and its green is not the
gate's green. Run `composer run qa:full` before opening a pull request.

The `guarantees` suite holds the product properties listed in the README. Each one is a test that fails on a
real violation. Verify that for yourself by introducing one, running the suite, and reverting.

## Method

Read [`testing.md`](testing.md) before writing a test. The short version: the test is written first and run in
the failing state, it follows Arrange, Act, Assert, and it asserts on behaviour observable from outside the unit
rather than on the steps the unit took.

## Layout

[`architecture.md`](architecture.md) describes the modules and what each owns. [`adr/`](adr/) records the
decisions and, for each one, the check that makes it hold.
