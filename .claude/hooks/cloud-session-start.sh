#!/usr/bin/env bash
# Prepares project dependencies for Claude Code cloud sessions.
# System packages (PHP 8.4, MariaDB, the mariadb/redis host aliases) come from
# the cloud environment's setup script; this hook handles everything that is
# versioned in the repo. Local (Sail) sessions skip it entirely.
set -euo pipefail

if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
    exit 0
fi

cd "${CLAUDE_PROJECT_DIR:-$(git rev-parse --show-toplevel)}"

if ! php -r 'exit(PHP_VERSION_ID >= 80401 ? 0 : 1);' 2>/dev/null; then
    echo "cloud-session-start: PHP 8.4.1+ is required by composer.lock but $(php -r 'echo PHP_VERSION;' 2>/dev/null || echo 'none') is installed." >&2
    echo "cloud-session-start: add PHP 8.4 to the cloud environment's setup script. Skipping dependency install." >&2
    exit 0
fi

if command -v service >/dev/null 2>&1 && [ -e /etc/init.d/mariadb ]; then
    echo "cloud-session-start: starting MariaDB"
    service mariadb start >/dev/null || echo "cloud-session-start: MariaDB failed to start" >&2
fi

echo "cloud-session-start: composer install"
composer install --no-interaction --prefer-dist --no-progress

if [ ! -f .env ]; then
    echo "cloud-session-start: creating .env"
    cp .env.example .env
    php artisan key:generate --no-interaction
fi

echo "cloud-session-start: npm ci && npm run build"
npm ci --no-audit --no-fund
npm run build

php artisan config:clear --no-interaction

echo "cloud-session-start: ready"
