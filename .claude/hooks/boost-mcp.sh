#!/usr/bin/env bash
# Starts the Laravel Boost MCP server via Sail locally, or directly in
# Claude Code cloud sessions where there is no Docker daemon.
set -euo pipefail

cd "${CLAUDE_PROJECT_DIR:-$(dirname "$0")/../..}"

if [ "${CLAUDE_CODE_REMOTE:-}" = "true" ]; then
    exec php artisan boost:mcp
fi

exec vendor/bin/sail artisan boost:mcp
