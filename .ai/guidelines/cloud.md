# Claude Code Cloud Sessions

## Drop the `vendor/bin/sail` prefix when `CLAUDE_CODE_REMOTE=true`

Cloud sessions have no Docker daemon, so Sail cannot run. PHP 8.4, MariaDB and Redis are installed natively by the cloud environment's setup script, which also maps the `mariadb` and `redis` hostnames to `127.0.0.1` so `phpunit.xml` and `.env.testing` work unchanged. The `.claude/hooks/cloud-session-start.sh` SessionStart hook runs `composer install`, `npm ci` and `npm run build`.

Run every command these guidelines give with Sail, without the prefix:

- Tests: `php artisan test --display-phpunit-notices` (filtered: `php artisan test --compact --display-phpunit-notices --filter=...`)
- Artisan: `php artisan ...`
- Pint: `vendor/bin/pint --dirty --format agent`
- Composer / Node: `composer ...`, `npm run build`

If the hook reports that PHP 8.4 is missing or MariaDB is unreachable, the environment setup script needs fixing — say so rather than mocking around it.
