# Test Group Vocabulary (canonical)

The test suite is tagged with PHPUnit `#[Group]` attributes on two axes so tests
can be selected by logical subset. This is the **canonical, reconciled
vocabulary** that all suites use. It was produced by reconciling the four
per-suite manifests (Unit, Feature, SmokeTest, and the `new-characters` delta);
all four scopes independently converged on the same kebab-case names, so no
renames were required.

## How to run a group

```bash
vendor/bin/sail artisan test --group=<name> --compact
```

Combine with a suite for a narrower run:

```bash
vendor/bin/sail artisan test --testsuite=Feature --group=authorization --compact
```

## Axes

- **Domain groups** are applied at **class level** (at least one per class). A
  class carries more than one when its subject genuinely spans domains — e.g. a
  job that fetches data from Blizzard gets both its feature domain (`characters`)
  **and** `blizzard-integration`. A domain may also be applied at method level
  when only a subset of a class's methods touch that domain.
- **Behaviour groups** are applied at **method level** and are optional. They are
  added only where the behaviour axis is selective (a reviewer would plausibly
  run that slice), not forced onto every method.

## Domain groups

| Group | Definition |
|-------|------------|
| `characters` | Character roster, profile, edit/show, character models/resources, roster-sync and portrait jobs, GRM upload processing. |
| `raiding` | Raids, events, assignments, attendance, phases, templates, planned absences, boss strategies, spells, reports (raid-facing). |
| `loot` | Loot council comments/reactions/cache, items, priorities, bias tool. |
| `daily-quests` | Daily quest CRUD, audit, seeders, notifications, stale-message cleanup. |
| `blizzard-integration` | Blizzard Game Data / Render API connectors, requests, responses, data objects, CDN mirror, media/icon endpoints. |
| `raidhelper-integration` | Raid-Helper connector, webhooks, composition/event sync, RH requests. |
| `warcraftlogs-integration` | Warcraft Logs service, value objects, report links/fetch jobs, report cache. |
| `discord-integration` | Discord client/resources/payloads, role/user sync, Discord-delivered notifications, Discord auth. |
| `auth` | Authentication, accounts/profile, roles/ranks/permissions, view-as-role, policies, channel auth. |
| `dashboard` | Officer-dashboard page smoke loads (used only where a class is purely dashboard-page-oriented). |
| `broadcasting` | Reverb/Echo broadcast events, channel authorisation, broadcast-on-retry behaviour. |
| `media` | Media-library path generators, icon serving and attachment, portraits. |
| `platform` | Cross-cutting infrastructure with no single feature owner: middleware, providers, casts, enums, helpers, prunable models, generic resources, addon export, public pages. |

## Behaviour groups

| Group | Definition |
|-------|------------|
| `happy-path` | Nominal success flow — page loads / canonical redirect, job attaches/syncs correctly, success-path assertions. |
| `error-handling` | Error/exception paths and resilience to failing upstreams (asserts a throw, or graceful skip on an upstream failure). |
| `validation` | Form-request / input validation failures (`assertInvalid`, `assertSessionHasErrors`, `assertJsonValidationErrors`, 422). |
| `authorization` | Access-control denials — guest redirect to login, `assertForbidden`/`assertUnauthorized`, 401/403. |
| `contract` | Structural guarantees with no I/O — job/queue contract (tries, backoff, tags, overlap lock, middleware), resource output shape & sort order, broadcast channel/payload shape. Supersedes the legacy `job-contract` / `listener-contract`. |
| `edge-case` | *Reserved.* Boundary conditions, empty/null states, unusual inputs. Defined for completeness but not currently applied by any suite. |

## Legacy tag folding

Six files were tagged before this work with an older, method-specific
vocabulary. Those tags were folded into the canonical scheme (not preserved
verbatim). Representative folds:

- `job-contract`, `listener-contract`, `middleware` → `contract`
- `handle`, `uri-input`, `filename`, `gender` (success), `fallback-url`,
  `retail-asset-url`, `character-synchronisation`, `resolve-base-url`,
  `namespace`, `default-slugs`, `oauth` → `happy-path`
- `exception-mapping`, `rate-limits`, and the throwing/`gender`-skips paths →
  `error-handling`
- `locale-validation` → `validation`
- `broadcast` → `broadcasting` (domain)
- the ad-hoc `notifications` class tag → dropped in favour of domain tags
  (`loot` + `discord-integration` + `blizzard-integration`)

## Scope note

The stable suites (`Unit`, `Feature`, `SmokeTest`, off `main`) and the
`new-characters` test delta were tagged on separate branches with disjoint file
sets, then integrated. The `dashboard` and `edge-case` groups appear in this
canonical set even though the `main`-based integration branch may not exercise
`dashboard` directly — it is carried by the `new-characters` delta side.
