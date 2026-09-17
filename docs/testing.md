# Testing

A test suite is only worth what it would catch. This document states the method, the structure, what is
forbidden, and the gates that must be green before anything merges.

## Method: the test fails first

A test is written before the code that satisfies it, and it is **run in the failing state** before that code
exists. Watching it fail is the only evidence that it tests something. A test written afterwards against code
that already passes proves that the code does what it does.

Where a defect is fixed, the sequence is the same: a test that reproduces the defect, run and seen to fail,
then the fix, then the test passing. A fix without a failing reproduction is a claim.

## Structure: Arrange, Act, Assert

Every test has three visible parts, in order, separated by blank lines.

- **Arrange** builds the world the test needs.
- **Act** performs exactly one action.
- **Assert** checks the observable result of that action.

No branching and no loops in the body of a test. If a test needs an `if`, it is two tests. If it needs a loop
over cases, it is a data-driven test with the cases as data.

One behaviour per test, and the test name says which behaviour in plain words.

## Forbidden: the tautological test

A test that reproduces the implementation in order to compare it with itself measures nothing while reporting
success. Three concrete forms, all rejected in review:

- **Asserting on a double the test just configured.** Telling a mock to return a value and then asserting that
  the value came back tests the mocking library.
- **Recomputing the expectation with the formula under test.** The expected value is written literally, or
  derived from an independent source. If the expected value is too tedious to write by hand, that is a signal
  the unit is too large.
- **Verifying that a setter sets.** Accessors carrying no logic are exercised by the tests of the behaviour
  that uses them, not by tests of their own.

The rule underneath all three: **a test asserts on behaviour observable from outside the unit**, never on the
steps the unit took internally. Asserting on internal call order couples the test to the implementation and
makes refactoring fail the suite for no reason.

## Property-based tests

Where the domain states an invariant, the invariant is tested against a whole corpus or generated input
rather than against a handful of examples. Each arrives with its milestone; those not yet written are a
commitment rather than a claim.

| Invariant | Milestone | Test |
|---|---|---|
| Resolving a keyword to a taxonomy code is idempotent | M1 | `ResolutionTest`, on the fixture |
| Every preferred label of every concept in every language leads to that concept and never to another | M1 | `ResolutionTest`, over every label of the fixture |
| A left swipe never produces a match | M4 | |
| A screening answer never closes a conversation | M4 | |
| An export covers every action in its period and none outside it | M7 | |

## Mutation testing

Line coverage says which lines ran. Mutation testing says which lines are actually checked. The suite is run
against deliberately altered code, and a mutant that survives marks a line no assertion covers.

One threshold applies to `src/`, `bootstrap/` and `routes/`, the wiring included, since a mutant nobody kills
there means the integration suite is not checking the assembly it exists to protect. The taxonomy module is
measured under it like the shared one.

Per-module thresholds arrive with the modules where a silent defect is expensive: identity, matching, data
protection and exports will each carry a higher one than the rest.

## Test types and where they live

| Type | Scope | Speed |
|---|---|---|
| Unit | One module, no I/O, no database | Milliseconds |
| Integration | Real PostgreSQL, real queue, real storage adapter | Seconds |
| Contract | Generated specification against the frozen one, and both client generators against it | Seconds |
| End-to-end | The web application driven through a browser | Tens of seconds |
| Accessibility | Automated rules across every rendered surface | Seconds |

A unit test that touches the database is an integration test in the wrong directory.

## Product guarantees expressed as tests

The guarantees the README lists are collected in the `guarantees` suite so a reader can find them in one place,
and each names the decision record it enforces.

**A guarantee is added in the milestone that adds the behaviour it protects, never before.** A test asserting a
property of code that does not exist is a placeholder that reports success, which is worse than an admitted
gap. The README states which guarantees hold today and which milestone enforces each of the rest.

Enforced today:

| Guarantee | Enforces |
|---|---|
| The product name reaches the interface from exactly one place | ADR-0004 |
| Every tracked file that can carry user-visible text is inside that scan | ADR-0004 |
| Rendering under a different name leaves no trace of the previous one | ADR-0004 |
| The framework requirement names one commit and no version range, and the lock file agrees | ADR-0002 |
| Declared extensions cover what the dependencies need, every workflow installs them, and the setup guide lists them | none |
| A configuration file takes a name the framework reserves only when it is that framework object, and loads as one | none |
| Every module under `src/` is fenced by the boundary check, and every fence has its module | ADR-0002 |
| Every workflow action is pinned to a commit and keeps its version in a comment | none |
| No tracked file carries an absolute path from a developer's machine | the publication rule in CONTRIBUTING.md |
| The application boots and answers its routes | none |

Each of the remaining guarantees is listed in the README against the milestone that will enforce it. When that
milestone lands, the guarantee moves into this table with the test that proves it.

### Writing one

A guarantee test differs from an ordinary test in one respect: **it must be shown to fail on a real
violation before it is trusted.** Introduce the violation, run the suite, watch it fail and name the offending
file, then revert. A guarantee nobody has seen fail is a guarantee nobody has verified.

Where the guarantee is a scan, it also needs a companion assertion proving the scan reaches real files.
A scan whose configuration is wrong matches nothing and reports success.

## Gate integrity

A gate that reports a false green is worse than no gate. These rules are not style preferences.

- **Never pipe before reading the exit code.** A piped command reports the last stage's status. Write to a
  file, read the status, then inspect the file.
- **Never run a gate with a partial configuration.** A scoped run answers a different question, and its green
  is not the gate's green.
- **Never generalise from a slice to the whole.**
- **Never truncate a measurement.** No head, no tail, no result limit on a search whose purpose is to count or
  enumerate. A truncated enumeration is a different answer that looks like the real one.
- **Never run two heavy measurements at once.** Contention reads as a result.
- **Never silence a finding.** No suppression annotation, no file exclusion, no baseline entry added to make a
  gate pass. Fix the cause.

## The gate sequence

Run in this order; each must be green before the next means anything. A step arrives with the milestone that
gives it something to check. A gate with nothing to measure reports success and teaches everyone to trust a
green that means nothing.

| # | Step | Command today |
|---|---|---|
| 1 | Formatting | `composer run cs:check` |
| 2 | Static analysis at maximum level, no suppressions | `composer run phpstan` |
| 3 | Module boundary check, every dependency declared | `composer run boundary:check` |
| 4 | Design source and generated themes agree | `composer run design:check` |
| 5 | Unit tests | `vendor/bin/phpunit --testsuite unit` |
| 6 | Integration tests, against a real PostgreSQL | `vendor/bin/phpunit --testsuite integration` |
| 7 | Contract tests and backward-compatibility detection | M4, when the contract exists |
| 8 | Product guarantee suite | `vendor/bin/phpunit --testsuite guarantees` |
| 9 | End-to-end and accessibility tests | M4, when there are screens to drive |
| 10 | Mutation testing against the per-module thresholds | `composer run mutation` |
| 11 | Dependency audit | `composer run audit:deps` |

Steps 1 to 6 and 8 run together as `composer run qa`, preceded by a check that the manifest and the lock file
agree. Adding 10 and 11 gives `composer run qa:full`, which is what runs before a milestone is declared done.

### The boundary check

Modules live under `src/`, one directory each. Inside a module, anything under `Internal\` or `Features\` is
private; the rest is the module's public surface. `deptrac.yaml` names each module as two layers and fixes
which may depend on which: a module on its own internals, `Shared` on nothing else but the framework, every
product module on `Shared` and the framework, and one composition root (the module registry) on everything,
by name. No layer reaches another module's internals.

The check runs with every dependency required to be declared, so a module that reaches a class outside any
layer fails the gate rather than being noted. A guarantee test keeps the configuration and the directory tree
in step in both directions: a module without its two layers fails, and a layer without its module fails.

## What "done" means

A change is done when the full sequence has been run, not a subset and not a scoped run, and the command that
proves it can be quoted. A result announced without the command that produced it is not a result.
