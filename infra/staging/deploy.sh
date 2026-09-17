#!/bin/sh
# Deploys the server application on the staging host.
#
# The control panel on the host pulls this repository on every push to the `staging` branch and then runs
# this script from the deployment directory. Keeping the script here rather than in the panel means what
# happens on the host is reviewed as a diff, and the panel's only line is:
#
#     sh infra/staging/deploy.sh https://staging.bothdecks.com
#
# The argument is optional. When it is given, the last step confirms from the host itself that the site
# answers over TLS and refuses an unauthenticated request, which is the condition the hosting decision puts
# on staging (docs/adr/0009-hosting-in-the-paris-region.md).
#
# Plain sh on purpose: the panel runs actions under /bin/sh, and a bash construction there fails on line one
# with a message that names nothing.
set -eu

site_url="${1:-}"

# The panel runs actions in the deployment directory. If this is not it, say so before anything else runs.
if [ ! -f apps/server/composer.json ]; then
    echo "Unexpected working directory: $PWD"
    ls -la
    exit 1
fi

cd apps/server
mkdir -p var

# The manifest requires PHP 8.5. The binary is chosen by the version it reports, never by an assumed path.
php_bin=""
for candidate in /opt/plesk/php/*/bin/php /usr/bin/php8.* /usr/local/bin/php /usr/bin/php; do
    if [ -x "$candidate" ] && "$candidate" -r 'exit(PHP_VERSION_ID >= 80500 ? 0 : 1);' >/dev/null 2>&1; then
        php_bin="$candidate"
        break
    fi
done

if [ -z "$php_bin" ]; then
    echo "No PHP 8.5 or later on this host. Binaries present:"
    ls -1d /opt/plesk/php/*/bin/php /usr/bin/php* 2>/dev/null || echo "  none"
    exit 1
fi

# Composer has to run under that same PHP: launched by another, it evaluates the platform requirements with
# the wrong version and refuses to install.
composer_phar=""
for candidate in /usr/lib/plesk-9.0/composer.phar "$HOME/composer.phar" /usr/bin/composer /usr/local/bin/composer; do
    if [ -f "$candidate" ]; then
        composer_phar="$candidate"
        break
    fi
done

if [ -z "$composer_phar" ]; then
    echo "Composer not found. Install the panel's Composer extension, or place composer.phar in $HOME."
    exit 1
fi

echo "PHP $("$php_bin" -r 'echo PHP_VERSION;') via $php_bin"
echo "Composer via $composer_phar"

COMPOSER_HOME="$HOME/.composer"
export COMPOSER_HOME

"$php_bin" "$composer_phar" install \
    --no-dev --no-interaction --prefer-dist \
    --optimize-autoloader --no-progress --no-ansi

# The kernel has to assemble before the domain serves anything. Without this line, a missing dependency or
# an invalid configuration is discovered as a 500 on the first visit rather than in this log.
"$php_bin" -r 'require "vendor/autoload.php"; (require "bootstrap/app.php")(); echo "kernel assembled", PHP_EOL;'

# The schema is brought up to the revision being deployed, and the result is listed so this log says which
# migrations the host now carries. Each migration runs in its own transaction, so one that fails leaves the
# schema at the previous migration and stops the deployment here, in the log, rather than on a request.
"$php_bin" bin/bothdecks migrate:run
"$php_bin" bin/bothdecks migrate:status

# The static assets a page links are copied into the document root; nothing under public/assets is tracked.
"$php_bin" tools/publish-assets.php

# The taxonomy is loaded once per host by a person, because a snapshot takes an hour of requests to the
# source and a deployment must not. Its absence is stated here rather than discovered on the first page.
if ! "$php_bin" bin/bothdecks taxonomy:report --json > /dev/null 2>&1; then
    echo "No taxonomy version is current on this host. Once, as the system user: bin/bothdecks taxonomy:snapshot, then taxonomy:import."
fi

if [ -z "$site_url" ]; then
    echo "No site URL given; the check that the site answers and refuses an unauthenticated request was not run."
    exit 0
fi

# Staging is reachable only behind authentication. An unauthenticated request to the health route must be
# refused with 401: a 200 here means the protection is off, and a 404 means the router is not being reached.
status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$site_url/health" || echo "000")"
case "$status" in
    401)
        echo "$site_url/health answers over TLS and refuses an unauthenticated request (401)."
        ;;
    *)
        echo "$site_url/health returned $status; expected 401. The site is either unprotected, not routed, or not reachable."
        exit 1
        ;;
esac
