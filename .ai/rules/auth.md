---
paths:
  - 'tests/**,routes/auth.php,app/Http/Controllers/Auth/**'
---

# Auth

## Browser-testing authenticated routes: use the local user login flow
When you need an authenticated session for browser tests (Playwright/Dusk) or manual browser exploration in the `local` or `testing` environment, do NOT stub Socialite, hand-craft sessions, or rely on `actingAs` outside PHP feature tests. Use the built-in manual login flow.

## How it works
- `GET /login/local` (route name `login.local`) renders `Auth/LocalLogin`; `POST /login/local` (`login.local.store`) logs in an existing user by `id`.
- Controller: `App\Http\Controllers\Auth\LocalLoginController` — validates `id` against `exists:users,id`, calls `Auth::login(User::findOrFail($id))`, redirects to `intended('/')`.
- Registered in `routes/auth.php` under the "Manual Login Routes" group, gated to `local`/`testing` only. These routes do not exist in production.

## Seeded test users
- `database/seeders/LocalUserSeeder` provisions one user per Discord role plus a site admin, keyed by IDs from `config('auth.local_users')`.
- Config keys: `officer`, `loot_councillor`, `raider`, `member`, `guest`, `admin` — each from env vars `LOCAL_USER_*_ID` (e.g. `LOCAL_USER_OFFICER_ID`), set in `.env.testing`.
- The same config IDs drive `ViewAsRoleController`'s role impersonation.

## Recipe
1. Ensure `LocalUserSeeder` has run and the `LOCAL_USER_*_ID` env vars are set.
2. Navigate to `/login/local`, submit the target user's `id` from `config('auth.local_users.<role>')`, then visit the authenticated route under test.
3. Choose the user whose role matches the authorization being exercised (e.g. `officer` for officer-gated pages, `guest` for the unprivileged path).
