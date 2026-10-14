#!/bin/sh
# Secrets come from environment variables if given, otherwise from the files the
# "secrets" container generated (same files Laravel reads, so the values match).
set -e
export AUDIT_SIGNING_SECRET="${AUDIT_SIGNING_SECRET:-$(cat /secrets/signing_secret)}"
export AUDIT_READ_API_KEY="${AUDIT_READ_API_KEY:-$(cat /secrets/read_api_key)}"
exec "$@"
