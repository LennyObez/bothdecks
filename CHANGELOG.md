# Changelog

Notable changes to Both Decks. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning
follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Repository foundation: layout, licence, contribution, conduct and security policies, and the publication rule
  that a tracked file states what the code guarantees.
- Architecture decision records for the seven decisions the product rests on: one repository, the framework,
  European-only model inference, the codename and its replaceability, codes rather than strings, reciprocal
  decks with an asymmetric history, and screening that orders without deciding.
- Server on the framework, pinned to an exact commit rather than a version range, with a liveness endpoint that
  deliberately checks no dependency.
- Application assembly in `bootstrap/app.php`, shared by the front controller and the integration tests, so a
  service without a container binding fails in the suite rather than in production.
- Application identity as a single configuration value, validated on construction: a closed set of
  environments, so an unrecognised one is refused instead of silently treated as non-production, and an
  absolute URL, so exports and notifications cannot build broken links.
- Front controller that fixes the project root and the error-display policy before anything can fail, and that
  cannot leak an unhandled throwable to the client.
- Integration tests that dispatch a real request through the kernel, and a smoke test through the front
  controller over real HTTP on a kernel-assigned port.
- Product guarantees, each watched failing against a deliberate violation before being trusted: the product
  name reaches the interface from one place; every tracked file that can carry user-visible text is inside that
  scan; rendering under a different name leaves no trace; the framework requirement names one commit; declared
  extensions match what the dependencies need and what every pipeline installs; no configuration file takes a
  name the framework reserves; every workflow action is pinned to a commit; no tracked file carries an
  absolute path from a developer's machine.
- Gate sequence: formatting, static analysis at maximum level with no suppressions, every test suite, mutation
  testing over the wiring as well as the classes, and a dependency audit.
- Continuous integration with per-artefact path filters, a repository-wide job for the guarantees, a check that
  discovers directories from git and fails when one holds unbuilt source, and a check that every relative
  documentation link resolves.
- Nightly pipeline replaying the behaviour suites against the framework's branch head without disturbing the
  pinned manifest. It reports; it never blocks a merge.
- Declared label set, applied from the repository on change and weekly.
- Roadmap covering eleven milestones from foundation to launch, and a README that separates the guarantees
  enforced today from those committed to, each against the milestone that will enforce it.
