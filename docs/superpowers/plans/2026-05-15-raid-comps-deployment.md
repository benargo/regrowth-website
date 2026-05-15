# Raid Comps Branch — Production Deployment Plan

> **Note:** This is an operational deployment checklist, not an implementation plan. Execute each step manually against the production environment in the order listed.

**Goal:** Deploy the `raid-comps` branch to production, covering all schema changes, new package installs, seeder runs, and environment variable additions.

**Architecture:** This branch introduces the raid composition assignment system (event assignment groups + assignments), the spatie/laravel-medialibrary integration, playable classes synced from the Blizzard API, target markers, and spells. Eleven new migrations rename legacy tables, create new tables, and extend existing ones.

**Tech Stack:** Laravel 13, Inertia v3/React 19, spatie/laravel-medialibrary v11, spatie/laravel-query-builder v7, Blizzard Battle.net API.

---

## Context

The `raid-comps` branch adds the following production-facing changes relative to `main`:

- **11 new migrations** — renames `tbc_*` tables to bare names, creates `events`, `event_assignments`, `event_assignment_groups`, `playable_classes`, `target_markers`, `spells`, and `media` tables, drops `tbc_daily_quest_notifications`, adds `max_loot_councillors` to `raids`, adds `notes` to `bosses`, adds `level` and `playable_class_id` to `characters`, drops `playable_class` JSON column
- **3 new seeders** to run manually (`PlayableClassSeeder`, `TargetMarkerSeeder`, `CharacterSeeder`)
- **2 new Composer packages** (`spatie/laravel-medialibrary`, `spatie/laravel-query-builder`)
- **3 new env variables** (`RAID_HELPER_TOKEN`, `RAID_HELPER_SERVER_ID`, `RAID_HELPER_CHANNEL_IDS`)
- **1 new middleware** (`statefulApi()` added to `bootstrap/app.php`)
- **1 new service provider** (`RaidHelperServiceProvider`)
- **New public asset** (`public/images/targetmarkers.webp`)
- **Frontend JS bundle** must be rebuilt

---

## Staging Dry-Run (do this before touching production)

Run the full deployment sequence against a staging environment that has a copy of the production database. This validates every migration, seeder, and env variable before any production risk.

### Set up staging

- [ ] **1. Restore a production database dump to staging**

  ```bash
  # On staging server — drop and recreate the DB, then import prod dump
  mysql -u root -p -e "DROP DATABASE IF EXISTS regrowth_staging; CREATE DATABASE regrowth_staging;"
  mysql -u root -p regrowth_staging < prod_backup_$(date +%Y%m%d).sql
  ```

- [ ] **2. Point staging `.env` at the staging database**

  ```env
  DB_DATABASE=regrowth_staging
  APP_ENV=staging
  APP_DEBUG=true
  ```

  Also add the three new Raid Helper env variables (can use dummy values to test boot):
  ```env
  RAID_HELPER_TOKEN=test_token
  RAID_HELPER_SERVER_ID=000000000000000000
  RAID_HELPER_CHANNEL_IDS=000000000000000000
  ```

- [ ] **3. Deploy the `raid-comps` branch to staging**

  ```bash
  git pull origin raid-comps
  composer install --no-dev --optimize-autoloader
  ```

### Run the full sequence on staging

- [ ] **4. Run all migrations on staging**

  ```bash
  php artisan migrate --force
  ```

  Expected output: 11 migrations run successfully with no errors. If any migration fails, fix it here before touching production.

- [ ] **5. Run seeders on staging in order**

  ```bash
  php artisan db:seed --class=TargetMarkerSeeder --force
  php artisan db:seed --class=PlayableClassSeeder --force   # requires real Blizzard API creds
  php artisan db:seed --class=RaidSeeder --force
  php artisan app:refresh-guild-roster                      # populates playable_class_id + level
  ```

  Wait for the queued `UpdateCharacterFromRoster` jobs to process, then verify counts:
  ```bash
  php artisan tinker --execute 'echo \App\Models\TargetMarker::count() . " markers, " . \App\Models\PlayableClass::count() . " classes, " . \App\Models\Character::whereNotNull("playable_class_id")->count() . " enriched chars, " . \App\Models\Character::whereNotNull("level")->count() . " with level\n";'
  ```
  Expected: `8 markers, 13 classes, N enriched chars, N with level`

- [ ] **6. Build frontend assets on staging**

  ```bash
  npm ci && npm run build
  ```

- [ ] **7. Clear and warm caches on staging**

  ```bash
  php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
  ```

- [ ] **8. Smoke-test staging manually**

  - [ ] `/raiding` loads without errors
  - [ ] `/events/{id}/edit` — assignment editor renders correctly
  - [ ] Browser console is clean (no JS errors)
  - [ ] Class icons load from the media library
  - [ ] `php artisan pail` shows no exceptions during page loads

  **If all staging checks pass, proceed to production. If anything fails, fix on the branch and repeat the staging dry-run.**

---

## Pre-Deployment Checks

- [ ] **1. Confirm the current production branch**

  ```bash
  git log --oneline -5
  ```
  Ensure you are on a clean copy of `main` in production before merging.

- [ ] **2. Back up the production database**

  Take a full snapshot before doing anything. The migrations include destructive operations (dropping `playable_class` JSON column, dropping `tbc_daily_quest_notifications` table).

- [ ] **3. Confirm Blizzard API credentials are set in production `.env`**

  ```bash
  grep BLIZZARD .env
  ```
  Expected: `BLIZZARD_CLIENT_ID` and `BLIZZARD_CLIENT_SECRET` both set. `PlayableClassSeeder` will fail without them.

---

## Step 1 — Add New Environment Variables

Edit the production `.env` file and add:

```env
RAID_HELPER_TOKEN=<token>
RAID_HELPER_SERVER_ID=<discord_server_id>
RAID_HELPER_CHANNEL_IDS=<comma,separated,channel,ids>
```

These are required by `RaidHelperServiceProvider` and `RaidHelperClient`. The app will boot without them but event syncing from Raid Helper will not function.

---

## Step 2 — Deploy Code

```bash
git pull origin raid-comps   # or merge/deploy via your CI pipeline
```

---

## Step 3 — Install New Composer Packages

```bash
composer install --no-dev --optimize-autoloader
```

New packages introduced:
- `spatie/laravel-medialibrary ^11.22`
- `spatie/laravel-query-builder ^7.3`

---

## Step 4 — Run Migrations

The 11 new migrations must run in date order (which `artisan migrate` handles automatically). The order matters because of foreign key dependencies:

| Order | Migration | What it does |
|-------|-----------|--------------|
| 1 | `2026_04_29_221238_delete_daily_quest_notifications_table` | Drops `tbc_daily_quest_notifications` |
| 2 | `2026_05_02_142155_create_raid_events_table` | Creates `events`, `pivot_events_characters`, `pivot_events_raids` |
| 3 | `2026_05_04_142955_rename_tbc_tables_to_base_names` | Renames all `tbc_*` tables; manages FK constraints |
| 4 | `2026_05_06_121525_add_max_loot_councillors_column_to_raids_table` | Adds `max_loot_councillors` to `raids`, makes `max_players` nullable |
| 5 | `2026_05_06_134316_create_media_table` | Creates `media` table for spatie/laravel-medialibrary |
| 6 | `2026_05_06_172745_add_notes_column_to_bosses_table` | Adds `notes` (longText, nullable) to `bosses` |
| 7 | `2026_05_08_221328_create_target_markers_table` | Creates `target_markers` (slug PK, no timestamps) |
| 8 | `2026_05_08_223404_create_spells_table` | Creates `spells` (id, name, type enum) |
| 9 | `2026_05_08_231204_create_event_assignments_table` | Creates `event_assignments` (polymorphic left/right sides, FK to events, bosses) |
| 10 | `2026_05_09_161300_create_event_assignment_groups_table` | Creates `event_assignment_groups`; adds `group_id` FK to `event_assignments` |
| 11 | `2026_05_10_123123_create_playable_classes_table` | Creates `playable_classes`; adds `level` + `playable_class_id` FK to `characters`; drops `playable_class` JSON column |

```bash
php artisan migrate --force
```

> **Warning:** Migration 11 drops the `playable_class` JSON column from `characters`. Ensure the backup from Pre-Deployment step 2 is complete before running.

---

## Step 5 — Run Seeders (in Order)

These seeders are **not** in `DatabaseSeeder.php` and must be run manually. Run them in this exact order because `CharacterSeeder` depends on `playable_classes` rows existing.

### 5a — Seed Target Markers (no dependencies)

```bash
php artisan db:seed --class=TargetMarkerSeeder --force
```

Seeds the 8 static target marker rows: skull, cross, square, moon, triangle, diamond, circle, star.

### 5b — Seed Playable Classes (requires Blizzard API access)

```bash
php artisan db:seed --class=PlayableClassSeeder --force
```

Fetches all playable classes from the Blizzard Battle.net API and creates `PlayableClass` records. Also fetches and stores class icon images via the media library into the `blizzard_icons` collection.

**Verify it worked:**
```bash
php artisan tinker --execute 'echo \App\Models\PlayableClass::count() . " classes seeded\n";'
```
Expected: 13 classes seeded.

### 5c — Seed Updated Raids (max_loot_councillors values)

The `RaidSeeder` has been updated to populate the new `max_loot_councillors` column. Re-run it to set these values on existing production raids:

```bash
php artisan db:seed --class=RaidSeeder --force
```

Seeds/updates 9 raids with `max_loot_councillors` (3 for 10-man, 5 for 25-man).

### 5d — Enrich Characters with Class and Level Data

Run the `app:refresh-guild-roster` Artisan command. This fetches the full guild roster from the Blizzard API and dispatches `UpdateCharacterFromRoster` jobs for every member, which populate both `playable_class_id` and the new `level` column (skipping characters below level 60).

```bash
php artisan app:refresh-guild-roster
```

> **Note:** The command checks a Blizzard API response cache and will refuse to run if the roster was fetched recently. If you see _"The guild roster was fetched recently. Please wait for the cache to expire."_, wait for the cache TTL to pass and retry. The jobs are queued — ensure Horizon is running (Step 8) so they process.

`CharacterSeeder` also performs a similar enrichment via individual character profile lookups, but `app:refresh-guild-roster` is the canonical path and also sets `level`, which the seeder does not. You do not need to run `CharacterSeeder` separately.

**Verify it worked** (after queue jobs have processed):
```bash
php artisan tinker --execute 'echo \App\Models\Character::whereNotNull("playable_class_id")->count() . " characters have class\n";'
php artisan tinker --execute 'echo \App\Models\Character::whereNotNull("level")->count() . " characters have level\n";'
```

### 5e — (Optional) Re-seed Bosses and Permissions if needed

`BossSeeder` and `PermissionSeeder` have been updated. If production data for these is already correct, skip. If you want to ensure the updated permission set and boss list are applied:

```bash
php artisan db:seed --class=PermissionSeeder --force
php artisan db:seed --class=BossSeeder --force
```

`PermissionSeeder` now seeds 21 permissions across 5 categories and auto-assigns all to the Officer role. `BossSeeder` seeds 51 bosses; it is safe to re-run if bosses already exist (check the seeder's `updateOrCreate` logic first).

---

## Step 6 — Clear and Warm Caches

```bash
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan route:cache
php artisan view:clear
php artisan view:cache
php artisan event:clear
php artisan event:cache
```

The new `RaidHelperServiceProvider` and `statefulApi()` middleware registration must be picked up. Clear all caches to ensure this.

---

## Step 7 — Build Frontend Assets

The JS bundle must be rebuilt to include all new React components (`Assignments.jsx`, `AssignmentCellEditor.jsx`, `EditEvent.jsx`, etc.) and the `AssignmentCellHelpers.jsx` shared helpers module.

```bash
npm ci
npm run build
```

Or via Sail in local/staging:
```bash
vendor/bin/sail npm ci
vendor/bin/sail npm run build
```

The `public/images/targetmarkers.webp` asset is already committed and will be deployed with the code in Step 2.

---

## Step 8 — Restart Queue Workers

The new `FlushRaidingCache` listener and event broadcasting for assignment changes are dispatched via queued events. Restart Horizon (or your queue worker) to pick up the new listeners:

```bash
php artisan horizon:terminate
# Horizon supervisor will restart it automatically, or start it manually:
php artisan horizon
```

---

## Step 9 — Verify Deployment

- [ ] Visit `/raiding` — confirm the page loads without errors
- [ ] Visit `/events/{id}/edit` — confirm the assignment editor renders for a known event
- [ ] Check browser console for JS errors
- [ ] Check Laravel logs for any post-migration errors:
  ```bash
  php artisan pail
  ```
- [ ] Confirm `PlayableClass` icons are accessible (media library URLs resolve correctly)
- [ ] Confirm Raid Helper event sync is working if `RAID_HELPER_TOKEN` was freshly added

---

## Rollback Notes

If a migration fails partway through:

1. The migration runner will stop at the failing migration; earlier migrations are already applied.
2. Do **not** re-run `migrate:fresh` — this would wipe all production data.
3. Identify the failing migration by name, fix the issue, then re-run `php artisan migrate --force`.
4. If the `playable_class` JSON column drop (migration 11) has already run and data is lost, restore from the pre-deployment backup.

---

## Seeder Dependency Summary

```
TargetMarkerSeeder    — no dependencies, can run any time after migration 7
     ↓
PlayableClassSeeder   — requires Blizzard API credentials; run after migration 11
     ↓
CharacterSeeder       — requires PlayableClass rows to exist in DB
```

`RaidSeeder`, `PermissionSeeder`, `BossSeeder` are independent of the above chain.
