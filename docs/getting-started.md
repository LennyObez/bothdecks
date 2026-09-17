# Getting started

## Requirements

| Tool | Version |
|---|---|
| PHP | 8.5.1 or later, with `ctype`, `curl`, `dom`, `exif`, `fileinfo`, `gd`, `intl`, `libxml`, `mbstring`, `openssl`, `pdo`, `pdo_pgsql`, `simplexml`, `sodium`, `zip`, `zlib` |
| Composer | 2.10 or later |
| PostgreSQL | 16 or later, with the `vector` (pgvector) and `postgis` server extensions available |

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

## Database

Two databases: one to work against, one the test suite owns. The suite fixes its own database name in
`phpunit.xml` and will not run against any other, so the two can share a server and a role. The extensions
are created by a superuser once; the application's role never needs to be one.

```bash
sudo -u postgres psql -c "create role bothdecks login password '<choose one>'"
sudo -u postgres psql -c "create database bothdecks owner bothdecks"
sudo -u postgres psql -c "create database bothdecks_test owner bothdecks"
for db in bothdecks bothdecks_test; do
  sudo -u postgres psql -d "$db" -c "create extension vector" -c "create extension postgis"
done
```

Then copy [`.env.example`](../apps/server/.env.example) to `.env`, fill in the connection and generate the
master key as the file explains. The copy is ignored by git. A variable exported in the shell wins over the
file, which is how the pipeline runs without one.

Bring the schema up and confirm the application sees the database:

```bash
php bin/bothdecks migrate:run
php bin/bothdecks migrate:status
```

The list includes the framework's own migrations beside the product's: the framework ships the tables its
second-factor authentication needs, and the runner applies every migration it knows in one sequence.

## Taxonomy

The occupations and skills come from a snapshot of the reference classification, taken from its API and
imported as a version; [`taxonomy.md`](taxonomy.md) is the reference record. The whole sequence, from a
workstation:

```bash
php bin/bothdecks taxonomy:snapshot   # about an hour: every concept of the release, to var/taxonomy
php bin/bothdecks taxonomy:import     # about a minute
php bin/bothdecks taxonomy:embed      # needs the model server named in .env; minutes on a GPU
php bin/bothdecks taxonomy:bubbles    # a few minutes
php bin/bothdecks taxonomy:report
```

The vectors need an embedding model server. On a workstation, a local runtime serving `bge-m3` at the address
in `TAXONOMY_EMBEDDING_URL` does; in any hosted environment the provider is one under European law
([ADR-0003](adr/0003-eu-only-model-inference.md)). Without vectors the cascade still resolves exact and
close matches and asks otherwise, and the report says the vectors are missing.

The test suite does not need any of this: it imports a small fixture snapshot and stands in a deterministic
function for the model.

## Running it

```bash
php -S localhost:8080 -t public
```

`GET /health` reports that the application booted and can read its own identity. It deliberately checks no
dependency: a liveness probe that fails because a downstream service is slow causes the restart loop it was
meant to prevent.

`php bin/bothdecks list` shows every console command. The migration commands come from the framework; the
product's own commands are the ones each module lists in its module class, gathered by
`src/Shared/Module/ModuleRegistry.php`, and a command listed nowhere does not exist as far as the console is
concerned.

## Configuration

`config/identity.php` holds the application identity. The product name lives there and nowhere else. See
[ADR-0004](adr/0004-the-product-name-lives-in-one-place.md). The other files under `config/` take names the
framework reserves, on purpose, and a guarantee test proves each loads through the framework's own typed
object. Environment-specific values are read from the process, then from `.env`:

| Variable | Purpose | Default |
|---|---|---|
| `APP_ENV` | Active environment | `production` |
| `APP_DEBUG` | Show stack traces on error pages, whatever the environment; set it only on a workstation | `0` |
| `APP_URL` | Canonical public origin, used to build absolute URLs | `http://localhost:8080` |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | The PostgreSQL connection | `127.0.0.1`, `5432`, `bothdecks`, `bothdecks`, empty |
| `PULSAR_MASTER_KEY` | 32 bytes as 64 hexadecimal characters; encryption, signing and replay protection derive from it | none, and the security posture reports it |

## Running the gates

Run the whole sequence, not a subset. A scoped run answers a different question, and its green is not the
gate's green.

```bash
composer run cs:check         # formatting
composer run phpstan          # static analysis at max level, no suppressions
composer run boundary:check   # no module reaches another module's internals
composer run design:check     # the design source and everything generated from it agree
composer run test             # every suite, against the test database
```

Or all of them, in order, with the manifest check first:

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
