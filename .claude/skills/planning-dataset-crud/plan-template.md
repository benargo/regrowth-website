# Plan template

Fill every section, in this order. Replace `{…}` placeholders. Delete a row only when the condition in brackets is false.

````markdown
# {Model} Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** {One paragraph: who can do what at /manage/{kebab-plural}, what is refused.}

**Architecture:** {Which shared pieces it uses, what is new and per-model, what is extracted first, what is retired.}

**Tech Stack:** Laravel 13 (PHP 8.4), Inertia v3 + React, `@headlessui/react`, Tailwind v4, lorisleiva/laravel-actions, PHPUnit 12 via `vendor/bin/sail test`.

## Decisions

| # | Question | Decision | Source |
|---|---|---|---|
| D1 | … | … | User / Assumed / House rule |

## Global Constraints

- **URLs and names:** `/manage/{kebab-plural}`, `management.{kebab-plural}.*`, root `{Model}Controller`, `tests/Feature/Dashboard/{Model}ControllerTest.php`.
- **Authorisation:** {DatasetPolicy via DatasetModel + UsePolicy, or the kept policy and its abilities}. `#[Authorize]` attributes only.
- **Shared layer:** reuse the pieces listed in Architecture. Don't duplicate them, and don't change their public API unless a task says so.
- **Backend conventions:** `#[Fillable]`/`#[Hidden]`, `#[Scope]`, `Attribute::make`, UPPERCASE cases in new enums, facades over helpers (`Redirect::`, `URL::`), `Carbon::now()`, `DB::transaction(fn)`, Actions for business logic. Resource arrays return enum cases, never `->value`.
- **Frontend:** before any `.jsx` step, invoke `inertia-react-development`, `tailwindcss-development` and `frontend-design:frontend-design`. Search `resources/js/Components`, `Helpers` and `Hooks` first. Use theme tokens (`ink-*`, `ground-*`, `secondary-*`). HeadlessUI form primitives.
- **Accessibility:** WCAG 2.2 AA. See the Verification task checklist.
- **Tests:** `superpowers:test-driven-development` and the `testing-best-practices` skill. Watch every new test fail for the expected reason. `#[Test]`, no `test_` prefix, a class-level `#[Group]` (`.ai/rules/testing-groups.md`). Separators as `.ai/rules/testing.md` § Section Separators defines them. Helpers at the bottom. Factories with `for()`. Exact validation messages. No `assertOk()` before `assertInertia()`. On failure paths, assert nothing was persisted. Create files with `vendor/bin/sail artisan make:test --phpunit … --no-interaction`.
- **Commands:** `vendor/bin/sail test --compact --display-phpunit-notices --filter=…`. Full suite: `vendor/bin/sail artisan test --parallel --display-phpunit-notices` (`--parallel` first, no `--compact`). After PHP edits: `vendor/bin/sail bin pint --format agent {files}`. At the end: `graphify update .`.
- **Git and database:** never stage, commit, branch or run migrations. `--env=testing` for any manual Artisan run.
- **Inertia page files:** `testing.ensure_pages_exist` is on, so every asserted component needs its `.jsx` file in the same task.

## Review Focus

{3–8 numbered risks a reviewer should check, each "Pinned in Task N (`test_method_name`)". Always include: an in-use delete sent directly is refused; a write without the policy's permission is refused; an update that keeps a unique value passes; blank optional fields store NULL; plus every model-specific risk found in discovery (FK cascades, external name matching, caches, broadcasts).}

## File Structure

**Create**

| File | Responsibility |
| --- | --- |

**Modify**

| File | Change |
| --- | --- |

**Delete** [overhaul only]

| File | Replaced by |
| --- | --- |

---

### Task N: {name}

{Skills to invoke first, if any.}

**Files:**
- Create: …
- Modify: …
- Test: …

**Interfaces:**
- Consumes: {classes/methods from earlier tasks or the shared layer}
- Produces: {exact signatures and prop shapes later tasks rely on}

- [ ] **Step 1: Write the failing test** — full test code.
- [ ] **Step 2: Run it and watch it fail** — exact command and the expected failure.
- [ ] **Step 3: Implement** — full code.
- [ ] **Step 4: Run it and watch it pass** — exact command.
- [ ] **Step 5: Format** — Pint on the touched PHP files.
````

## Task order

Each task must be testable when it runs: routes and controller exist before a request is tested through them, and a page's imports exist before the page.

1. [if needed] Extract still-GameVersion-specific pieces (see `reference.md` § Not yet extracted). GameVersion tests unchanged, plus a test for each new class. Any task that adds a shared piece (here or later) also updates `reference.md`.
2. Model: `DatasetModel`/policy, `EditLockable` + `HasEditLock`, `TracksUsage` + `USAGE_RELATIONS`, any new relations, cache flushes. [`HasMedia`] media collection rules.
3. [wizard] `{Model}SetupStep` enum, with a test that every case's `{Component}.jsx` file exists (so that task creates placeholder section files too; there is no JS test runner, so this PHP test is the only automated check on them).
4. Resource `forManagement()` scope, with the exact-keys test pinning the default shape.
5. Routes, `Build{Model}Routes` and an empty controller class. [overhaul] When a new route reuses an old path or name, retire the old route here, in the same task.
6. Actions: `Update{Model}`, [steps] `Build{Model}Relationships`, form-option builders, any sync actions.
7. React building blocks that pages import: optional-prop additions to shared components, `{Model}Form.jsx` + `{model}FormData()`, step sections. No PHP test here; it is checked by `npm run build` and the Verification task.
8. Controller, one section per task: index (+ `Index.jsx`; [child] grouped by parent), create/store (+ `Store{Model}Request`, `Create.jsx`), edit + lock (+ `Edit.jsx`), [setup + review (+ pages)], update (+ `Update{Model}Request`), destroy.
9. [sortable child] Reorder step on the parent's Edit page.
10. [overhaul or retired inline creation] Retire the old endpoints, keys and pages (`routes/deprecated.php` + `DeprecatedRoutesTest`, stale-key tests). Delete the superseded files. Update callers of renamed routes.
11. [seeder overwrites edits] Seeder create-only change with a seeder test.
12. Dashboard card. No test step: it is a frontend-only change, checked in Verification.
13. Verification.

## Verification task (copy as written, then add model-specific checks)

1. Full suite (command above) and `vendor/bin/sail npm run build` both pass. Fix any PHPUnit notices.
2. Browser check with Playwright. Sign in through `/login/local` as `.ai/rules/auth.md` describes, with an officer who holds the policy's permission (e.g. `config('auth.local_users.officer')`, or Local Admin `100000000000000003`). Never sign in through Discord. The dev database holds real guild data, so create, edit and delete only on records the check itself created, and ask the user before saving anything else.
3. Walk through Index → Create → [each setup step → Review → Edit from Review returns to Review] → Edit autosave (no toast) → a second session sees the page read-only with the editor's name → delete refused while in use → delete succeeds once unused.
4. WCAG 2.2 AA, for every page:
   - Every control has a label, and groups use fieldset/legend (1.3.1).
   - Required fields are marked, with a sentence explaining the marker (3.3.2).
   - A field's error is linked to it with `aria-describedby`. After a failed submit, the error summary takes focus (3.3.1, 2.4.3).
   - Flash messages and the autosave status are announced (4.1.3).
   - Text contrast is at least `secondary-400` on `ground-*` (1.4.3).
   - Focus is visible and not covered (2.4.7, 2.4.11).
   - Targets are at least 24×24 px (2.5.8).
   - Pages reflow at 320 px and at 200% zoom (1.4.10).
   - Delete asks for confirmation, and a disabled delete explains why in text.
5. `graphify update .`
