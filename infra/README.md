# Infrastructure

> **Empty for now.** What follows describes what will live here and the rules it will follow. The first
> environment is staging, built in M0.

Declarative definitions for the environments the product runs in. They describe the machines and services in
files rather than in someone's memory, so an environment can be rebuilt, reviewed as a diff, and reproduced
identically rather than reconstructed by hand.

**Production** runs on providers under European law: compute, storage, content delivery, mail and model
inference alike ([ADR-0003](../docs/adr/0003-eu-only-model-inference.md)). That is a product constraint, not a
preference. The provider registry that enforces it is written in M2, with the first model call; that record
says so rather than claiming a check that does not exist.

**Staging** is deliberately outside that rule, on an existing virtual machine in the Paris region
([ADR-0008](../docs/adr/0008-staging-runs-on-existing-infrastructure.md)). It holds synthetic data only, so
there is no personal data for the rule to protect. The three conditions that make this sound are stated in that
record, and if any stops holding, the environment falls back under ADR-0003.

The tooling is deliberately not chosen yet. One machine and one deployment do not justify a full
infrastructure-description tool; a configuration-management playbook or a documented deployment script says the
same thing in fewer moving parts. That calculation changes when the database and the object store arrive, and
the choice is made then rather than assumed now.

Two operating rules apply from the first deployment:

- A backup is restored automatically into a disposable environment once a week. A backup that has never been
  restored is not a backup.
- The staging environment is populated with synthetic data, never an extract of production.

Nothing in this directory is tracked with secrets in it: state files, variable files and keys are excluded in
`.gitignore`, and deployments authenticate through federated identity rather than long-lived credentials.

Populated in **M0** onward, as each environment becomes necessary.
