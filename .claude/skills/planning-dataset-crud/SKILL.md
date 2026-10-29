---
name: planning-dataset-crud
description: Use when asked to add, expand or overhaul officer management (create, edit, delete, index pages) for a dataset model in this app, such as Phase, Raid, Boss, PlayableClass or any seeded lookup model, or when a model has a partial Dashboard controller that needs the full CRUD cycle GameVersion has.
---

# Planning dataset CRUD

## Overview

GameVersion is the reference implementation of officer dataset management: Index cards, Create (plus a setup wizard and Review when the model has relationship steps), an autosaving edit-locked Edit page, and a delete that is refused while the record is in use. Most of it already lives in a shared layer. This skill turns a model name (the skill argument) into an **interview**, then a **TDD implementation plan** in the house format, built on that layer.

The output is a plan document. Do not write production code while using this skill.

**REQUIRED BACKGROUND:** read `reference.md` (the shared layer and the house decisions) before discovery. The plan's shape is fixed by `plan-template.md`. The interview checklist is `interview.md`.

## Process

1. **Discover.** Read `.ai/rules/index.md` and the matching rule files. Then, for the target model, collect every item in `reference.md` § Discovery checklist. Classify the job:
   - **New**: no management controller.
   - **Overhaul**: a controller or page already manages part of it. List every existing route, request, page and test.
   - **Child**: belongs to a parent dataset (Boss → Raid, Raid → Phase).
   - **Created inline elsewhere**: another dataset's `update` creates it through a `new_…` key (e.g. GameVersion's `new_raid`). The plan retires that key (see `reference.md`).

   A model can be several of these at once.
2. **Interview.** Ask the user every applicable question in `interview.md` with AskUserQuestion, at most 4 per call, with your recommended option first. Lead with what discovery found, e.g. "`bosses.raid_id` is RESTRICT, so…". If you cannot reach the user (you are a subagent), record each question with your assumed answer in the plan's Decisions table and mark it **Assumed**.
3. **Check the shared layer.** For every piece the plan needs, use the shared version from `reference.md`. If the only version is still GameVersion-specific (it is listed under "Not yet extracted"), the plan's **first tasks** extract it and move GameVersion onto it with GameVersion's tests unchanged. Never copy it into the new controller.
4. **Write the plan** to `docs/superpowers/plans/YYYY-MM-DD-<kebab-model>-management.md`, filling every section of `plan-template.md`. Each task is a red/green cycle with the full test code and the full implementation code, as in `docs/superpowers/plans/2026-09-23-game-version-relationships.md`.
5. **Self-check** against the Red flags below, then give the user the path and the list of Decisions marked Assumed.

## Red flags: stop and fix the plan

| Plan does this | Fix |
|---|---|
| Child model's Index is a flat grid | Group the cards under their parent (heading per parent, parent order, orphans group last when the FK is nullable) |
| Parent's setup step creates, renames or deletes child records | Children have their own CRUD. The parent's step only reorders or links them, and links to the child's Create/Edit pages |
| Copies `destroy` guards, store→first-step redirect or enum-option helpers into the new controller | Extraction task first (step 3) |
| Seeders not mentioned | Check each seeder for that model: re-running must not overwrite officer edits |
| `HasMedia` model has no upload field | Add one (medialibrary-development skill) |
| Overhaul leaves old endpoints or the old page in place | Fold in, then retire them through `routes/deprecated.php` (in the routes task if a new route reuses the old path or name) |
| New gate locks out officers who could use the old pages | Ask (interview) before narrowing access |
| A task adds a shared piece but `reference.md` isn't updated | Add the row in that task |
| Existing non-Dataset policy silently replaced or kept | Ask the user (interview) |
| Tests as bullet lists, or "add tests for X" | Full test methods with names, assertions and exact messages |
| Verification without WCAG 2.2 AA checks, or signs in through Discord | Use `plan-template.md` § Verification task as written |
| A commit, stage, branch or migration-run step | Remove it. The user reviews the diff and runs migrations |
| Plan written outside `docs/superpowers/plans/` | Move it |
