#!/bin/sh
#============================================================+
# File name   : test-entrypoint.sh
# Begin       : 2026-06-22
#
# Description : Entrypoint for the TCExam integration-test runner.
#               Waits for the database to accept connections, runs the
#               full PHPUnit suite (unit + DAL integration tests) and
#               hands the copied-back reports to the host user.
#
# (c) Copyright 2004-2026 Nicola Asuni - Tecnick.com LTD
# License: AGPL-3.0-or-later (see LICENSE).
#============================================================+

set -e

cd /workspace

# Materialise an installed configuration for unit tests that execute application
# entry points in child PHP processes. The application container performs the
# same setup independently for HTTP tests; the runner also needs it because
# installed config directories are intentionally excluded from the build context.
for area in shared admin public; do
    if [ ! -d "/workspace/${area}/config" ]; then
        cp -a "/workspace/${area}/config.default" "/workspace/${area}/config"
    fi
done

APP_HOST="${TCEXAM_APP_URL:-http://tcexam_app}"
SECRET="$(php -r 'echo bin2hex(random_bytes(32));')"

sed -i \
    -e "s|define('K_DATABASE_TYPE', '')|define('K_DATABASE_TYPE', '${TCEXAM_DB_TYPE}')|" \
    -e "s|define('K_DATABASE_HOST', '')|define('K_DATABASE_HOST', '${TCEXAM_DB_HOST}')|" \
    -e "s|define('K_DATABASE_PORT', '')|define('K_DATABASE_PORT', '${TCEXAM_DB_PORT}')|" \
    -e "s|define('K_DATABASE_NAME', '')|define('K_DATABASE_NAME', '${TCEXAM_DB_NAME}')|" \
    -e "s|define('K_DATABASE_USER_NAME', '')|define('K_DATABASE_USER_NAME', '${TCEXAM_DB_USER}')|" \
    -e "s|define('K_DATABASE_USER_PASSWORD', '')|define('K_DATABASE_USER_PASSWORD', '${TCEXAM_DB_PASSWORD}')|" \
    /workspace/shared/config/tce_db_config.php

sed -i \
    -e "s|define('K_PATH_HOST', '')|define('K_PATH_HOST', '${APP_HOST}')|" \
    -e "s|define('K_PATH_TCEXAM', '')|define('K_PATH_TCEXAM', '/')|" \
    -e "s|define('K_PATH_MAIN', '')|define('K_PATH_MAIN', '/workspace/')|" \
    /workspace/shared/config/tce_paths.php

sed -i \
    -e "s|define('K_RANDOM_SECURITY', '[^']*')|define('K_RANDOM_SECURITY', '${SECRET}')|" \
    -e "s|define('K_BRUTE_FORCE_DELAY_RATIO', [0-9]*)|define('K_BRUTE_FORCE_DELAY_RATIO', 0)|" \
    /workspace/shared/config/tce_config.php

# Wait for the database to accept a connection as the application user (the compose healthcheck
# already gates on the server being up; this also covers the seed data being loaded). The probe
# is dialect-aware so the same runner serves MySQL/MariaDB and PostgreSQL.
echo "[tcexam-test] waiting for ${TCEXAM_DB_TYPE} at ${TCEXAM_DB_HOST}:${TCEXAM_DB_PORT}…"
tries=0
until php -r '
    $t = getenv("TCEXAM_DB_TYPE"); $h = getenv("TCEXAM_DB_HOST"); $p = getenv("TCEXAM_DB_PORT");
    $u = getenv("TCEXAM_DB_USER"); $w = getenv("TCEXAM_DB_PASSWORD"); $d = getenv("TCEXAM_DB_NAME");
    if ($t === "POSTGRESQL") {
        exit(@pg_connect("host=$h port=$p dbname=$d user=$u password=$w connect_timeout=2") ? 0 : 1);
    }
    exit(@mysqli_connect($h, $u, $w, $d, (int) $p) ? 0 : 1);
'; do
    tries=$((tries + 1))
    if [ "$tries" -ge 60 ]; then
        echo "[tcexam-test] database not reachable after 60 attempts — aborting." >&2
        exit 1
    fi
    sleep 2
done
echo "[tcexam-test] database ready."

# phpunit.xml is git/docker-ignored (per-install); fall back to the committed .dist.
[ -f phpunit.xml ] || cp phpunit.xml.dist phpunit.xml

# Bind-mounted output directories (reports are copied back to the host through these).
mkdir -p target/coverage target/logs target/report

# Run every configured test suite (unit + integration). Do not abort on failure: we still want
# the reports copied back and the exit code propagated to the Makefile.
set +e
vendor/bin/phpunit --colors=never
status=$?
set -e

# Volumes mount as root; restore host ownership so reports stay editable and `make clean` works.
if [ -n "${HOST_UID}" ] && [ "${HOST_UID}" != "0" ]; then
    chown -R "${HOST_UID}:${HOST_GID:-${HOST_UID}}" target/coverage target/logs target/report 2>/dev/null || true
fi

exit "${status}"
