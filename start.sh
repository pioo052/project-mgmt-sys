#!/usr/bin/env bash
# URIMS entrypoint for Railway (Nixpacks) and any plain PHP container.
#
# 1. Applies database/schema.sql if tables are missing. This is idempotent and
#    non-destructive: existing tables and rows are never dropped or rewritten.
#    Set URIMS_SKIP_DB_BOOTSTRAP=1 to skip it (for example with an external
#    migration step).
# 2. Serves the application from the project root with PHP's built-in server.
#    PHP reads DB_HOST / DB_PORT / DB_NAME / DB_USER / DB_PASS directly from the
#    process environment, so no php-fpm clear_env workaround is needed.

set -euo pipefail

cd "$(dirname "$0")"

PORT="${PORT:-8080}"

if [ "${URIMS_SKIP_DB_BOOTSTRAP:-0}" != "1" ]; then
    if ! php database/install.php; then
        # Do not crash the container: the site serves the setup screen instead,
        # which reports the exact connection error and the variables it found.
        echo "URIMS: database bootstrap failed; continuing so the setup screen is reachable." >&2
    fi
fi

exec php -S "0.0.0.0:${PORT}" -t .
