# Infrastructure

Declarative definitions for the environments the product runs in. They describe the machines and services in
files rather than in someone's memory, so an environment can be rebuilt, reviewed as a diff, and reproduced
identically rather than reconstructed by hand.

**Every environment runs in the Paris region of one hyperscaler**, and the operator's jurisdiction is an
accepted, mitigated residual risk ([ADR-0009](../docs/adr/0009-hosting-in-the-paris-region.md)). Personal data
rests in the Union under a data processing agreement. Model inference is decided separately and stays on
infrastructure under European law ([ADR-0003](../docs/adr/0003-eu-only-model-inference.md)).

## Staging

The first environment. One virtual machine with a control panel, TLS and a process manager, serving
`staging.bothdecks.com` behind basic authentication.

Deployment is a pull of this repository by the panel on every push to the `staging` branch, followed by
[`staging/deploy.sh`](staging/deploy.sh). The script chooses PHP by the version it reports rather than by a
path, installs the server's dependencies under that PHP, assembles the kernel so a bad configuration fails in
the deployment log rather than on the first visit, and, given the site's address, confirms from the host that
the health route answers over TLS and refuses an unauthenticated request. The panel's own action is one line
that calls the script; nothing else is typed into it. [`docs/deployment.md`](../docs/deployment.md) carries the
panel settings and the steps a person performs.

Three conditions make staging what it is, and they are conditions rather than intentions:

1. **Staging never receives production data.** Not a dump, not an anonymised extract, not a single record. Its
   database, once there is one, is built from a generator committed to this repository.
2. **Staging holds no production secret.** It has its own credentials, its own signing keys and its own
   provider accounts, so a compromise there reaches nothing else.
3. **Staging is not reachable by the public.** It sits behind authentication, carries no index, and is not a
   soft route into anything.

## Production

The same region, provider and definitions, with its own credentials and keys. The production hostname points
at nothing until the first milestone that holds a user, and the mitigations in ADR-0009 are in place before it
does: encryption at rest with keys the owner holds, and the signed data processing agreement in the compliance
dossier.

## Tooling

Deliberately not chosen yet. One machine and one deployment do not justify a full infrastructure-description
tool; a documented script says the same thing in fewer moving parts. That calculation changes when the
database and the object store arrive in M1, and the choice is made then rather than assumed now.

Two operating rules apply from the first deployment:

- A backup is restored automatically into a disposable environment once a week. A backup that has never been
  restored is not a backup.
- The staging environment is populated with synthetic data, never an extract of production.

Nothing in this directory is tracked with secrets in it: state files, variable files and keys are excluded in
`.gitignore`, and deployments authenticate through federated identity rather than long-lived credentials.
