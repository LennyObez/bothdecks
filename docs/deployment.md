# Deployment

How a change reaches staging, what a person sets once, and what is checked before a deployment is called done.
Where the hosting decision comes from is [ADR-0009](adr/0009-hosting-in-the-paris-region.md); this page is the
procedure.

## The flow

1. Work lands on `main` through a pull request, with the four required checks green.
2. `main` is merged into `staging`, or a branch under review is pushed there. The server workflow runs on
   `staging` too, so the branch being deployed is measured, not only the branch it will be merged into.
3. The control panel on the staging host pulls the repository on that push and runs
   [`infra/staging/deploy.sh`](../infra/staging/deploy.sh).
4. The deployment is done when `https://staging.bothdecks.com/health` answers `200` to an authenticated
   request and `401` to an unauthenticated one. The script checks the second from the host; a person checks the
   first from outside.

Production deploys from a tag that has been read, never from what has just landed on `main`. It is not
enabled until the first milestone that holds a user.

## What the panel holds

Settings a person enters once in the control panel. Nothing here is a secret, and the secrets that exist,
the password file and the process environment, are created on the host and never pass through a conversation
or a repository.

### The repository

| Field | Value |
|---|---|
| Repository URL | `https://github.com/LennyObez/bothdecks.git` over HTTPS. The repository is public and the panel needs read access only; a read-only deploy key replaces this the day the repository goes private. |
| Repository name | `bothdecks-staging` |
| Branch | `staging` |
| Deployment mode | Automatic, with the panel's webhook address registered in the repository's webhook settings |
| Server path | `/bothdecks-staging` |
| Additional deployment actions | `sh infra/staging/deploy.sh https://staging.bothdecks.com` |

### The domain

| Field | Value |
|---|---|
| Document root | `bothdecks-staging/apps/server/public`, set in the domain's hosting settings. Never `httpdocs`: `config/`, `bootstrap/` and `vendor/` must stay outside the web root. |
| PHP support | FPM application served by nginx |
| Proxy mode | Off, so nginx serves directly |

### The web server

Additional nginx directives for the domain:

```nginx
index index.php;

# Anything that is not a file on disk goes to the router.
location / {
    try_files $uri /index.php$is_args$args;
}

# No file whose name starts with a dot is served.
location ~ /\. {
    deny all;
}

auth_basic "Both Decks staging";
auth_basic_user_file /var/www/vhosts/<subscription>/staging.bothdecks.com/.htpasswd;

# Without this exception the protection blocks certificate validation, and the certificate stops renewing
# sixty days later, in silence. The ^~ prefix takes precedence over the regular expression above it, so the
# order is guaranteed by nginx rather than by position in the file.
location ^~ /.well-known/acme-challenge/ {
    auth_basic off;
    allow all;
    try_files $uri =404;
}

add_header X-Robots-Tag "noindex, nofollow" always;
```

The panel's own password protection goes through Apache and does not apply in this mode, which is why
authentication is written here. The password file is created on the host, over SSH, by the person who holds
the password:

```sh
htpasswd -Bc /var/www/vhosts/<subscription>/staging.bothdecks.com/.htpasswd staging
```

### The database

A PostgreSQL 16 or later server reachable from the host, with a database owned by the application's role and
the `vector` and `postgis` extensions created in it. The extensions need a superuser once, at creation; the
application's role never needs to be one. The first migration checks for both and refuses to run where
either is missing, so a database that is not ready fails in the deployment log with the extension named.

### The model server

The taxonomy's vectors come from an embedding model reached over HTTP; `TAXONOMY_EMBEDDING_URL` names it
and `TAXONOMY_EMBEDDING_API_KEY` opens it. In every hosted environment it is a provider under European law,
never the workstation's local runtime ([ADR-0003](adr/0003-eu-only-model-inference.md)). Until one is
configured, the deployment script leaves the vectors uncomputed and the resolution cascade runs on labels
alone, which the version report shows; the staging host has none configured at this milestone.

### The process environment

The application reads its environment from the process first and from `apps/server/.env` second, with the
process winning. On the host the file is the simpler of the two: it is created once by hand, next to
`composer.json` and outside the document root, with mode `600`, from the tracked
[`.env.example`](../apps/server/.env.example). It carries the database connection and the master key, and
neither passes through the panel, a conversation or the repository.

The application reads the file in whichever process runs it, an FPM worker answering a request or the console
running the migrations from the deployment script, so the two see the same values without anything being
repeated in the panel. A value that must differ between the two, or that must not be in a file at all, is set
on the FPM pool; a `fastcgi_param` at the server level would be ignored once the panel's own PHP block defines
one, which is why it goes in the domain's PHP settings, under additional directives:

```
env[APP_ENV] = staging
env[APP_URL] = https://staging.bothdecks.com
env[APP_DEBUG] = 0
```

## What the script does

[`infra/staging/deploy.sh`](../infra/staging/deploy.sh), in order:

1. Refuses to run outside the deployment directory, naming where it is.
2. Chooses a PHP binary by the version it reports, never by an assumed path, and names it in the log.
3. Runs Composer under that same PHP, without development dependencies, with an optimised autoloader.
4. Assembles the kernel once, so a missing dependency or an invalid configuration fails in the deployment log
   rather than on the first visit.
5. Runs the pending migrations and lists the schema's state, so the log says which migrations the host now
   carries. Each migration runs in its own transaction; one that fails stops the deployment with the schema at
   the previous migration.
6. Copies the static assets into the document root, and says so when no taxonomy version is current on the
   host: loading one is done once, by a person, because it takes an hour of requests to the source.
7. Given the site address, requests the health route without credentials and requires `401`. A `200` means the
   protection is off; a `404` means the router is not reached; anything else means the site is not up.

The framework is installed from a version control repository, so Composer talks to the code host's API. An
unauthenticated client is limited to sixty calls an hour per address; if a deployment fails on that limit, a
token set with `composer config --global github-oauth.github.com` under the system user removes it.

## Checking from outside

From a machine that holds the staging credentials in `~/.netrc` (mode `600`, never in the repository):

```sh
curl -n -sS -o /dev/null -w '%{http_code}\n' https://staging.bothdecks.com/health
```

`200` is the condition on which the foundation milestone closes. Until it has been seen, the milestone's last
item stays open in [the roadmap](roadmap.md), because a deployment that has not been observed answering is a
deployment that has not happened.
