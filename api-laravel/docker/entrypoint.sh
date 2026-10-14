#!/bin/sh
# Runs before the container's main command (serve / queue:work / schedule:work).
set -e

# Secrets come from environment variables if given, otherwise from the files
# that the "secrets" container generated on first start (shared /secrets volume).
# So no secret is ever written into the repository.
export APP_KEY="${APP_KEY:-$(cat /secrets/app_key)}"
export AUDIT_SIGNING_SECRET="${AUDIT_SIGNING_SECRET:-$(cat /secrets/signing_secret)}"
export AUDIT_READ_API_KEY="${AUDIT_READ_API_KEY:-$(cat /secrets/read_api_key)}"

# Only the "api" container sets RUN_MIGRATIONS=1, so migrations run once, not three times.
# The seeder skips itself when users already exist, so restarts don't duplicate data.
if [ "$RUN_MIGRATIONS" = "1" ]; then
    php artisan migrate --force --seed
fi

# Replace this shell with the real command (so it receives stop signals directly).
exec "$@"
