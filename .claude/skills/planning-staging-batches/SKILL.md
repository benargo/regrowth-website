---
name: planning-staging-batches
description: Use when asked to group, batch or split unstaged, uncommitted or working-tree changes into commits, to say what to stage together, or to write a staging plan, commit plan or `git add` commands for a large diff in this repo.
---

# Planning staging batches

## Overview

Turn the working tree into a staging plan: a markdown file of small batches, each one logical change with its tests, a copy-paste `git add` command and a `git commit -m` command. The user stages and commits by hand.

**Read-only.** Never run `git add`, `git commit`, `git stash`, `git restore` or anything else that changes the index or working tree. The only file written is the staging plan.

**Batches come from what changed, not from where files live.** Boundaries come from implementation plan tasks first, then from diffs. A batch built from file names alone is a guess.

## Process

1. **List every changed file.** `git status --porcelain -uall` (`-uall` expands untracked directories). Keep this list; it is the coverage target. Run `git log --format=%s -15` for the commit message style.
2. **Find the implementation plans behind the changes.** Grep `docs/superpowers/plans/` (and `.archives/plans/` if it exists) for changed paths. Read each matching plan's `### Task N:` headings and `**Files:**` lists (Create / Modify / Test). Each task is a candidate batch, in plan order.
3. **Read the changes.** `git diff -- <file>` for every modified file; skim untracked files a task does not already explain. Assign files no task claims by what their diff does.
4. **Form batches** using the rules below.
5. **Write the plan** to `docs/superpowers/plans/YYYY-MM-DD-staging-<topic>.md` in the format below.
6. **Verify coverage** (below) and fix the plan until it passes.

## Batch rules

| Situation | Rule |
|---|---|
| Plan task exists | One task = one batch. Merge tasks only when they cannot be committed separately. Split a task only into parts that each stand alone as a commit (its review gates, or one action with its test), never into layers. |
| A later plan replaced an earlier one | The later plan's tasks win. Ignore tasks whose files are not in the working tree. |
| Files that reference each other in a cycle (models with mutual relations) | One batch for the whole cycle, as small as the cycle allows. Say in the description why it is large. |
| No plan covers a file | Group by what the diff does: one behaviour change per batch. |
| Tests | Go in the same batch as the code they test, even when they need a later wiring batch to pass. |
| Size | One logical change, however many files. A mechanical sweep (one rename across 30 files) is one batch. |
| File changed by several tasks | Whole files only, never `git add -p`. Put it in the **latest** batch whose code it references, so no commit references a class that is not committed yet. If an earlier batch needs it too, reorder the batches so everything the file references is committed by the batch it lands in. Say in its description which other batches' changes it carries. |
| Wiring file spanning many batches (`routes/*.php`, providers, `PermissionSeeder`, `bootstrap/app.php`) | Same rule: latest referenced batch. If it spans three or more, give it its own "wire up" batch straight after them, described by what it wires. In Context, name the batches whose feature tests only pass once the wiring batch is committed. |
| Order | Dependencies first: schema and models, then actions and jobs, then HTTP, then frontend, then docs. |
| File you cannot place | Read its diff again. Every batch has a specific subject; there is no "misc", "remaining" or "other changes" batch. |
| Deleted or renamed files | Old and new path in the same batch; `git add` takes deleted paths. |
| Should not be committed (secrets, dumps, logs, generated output) | List under **Left out** with the reason. No `git add`. |
| Already staged | List under **Already staged**, untouched. |

List explicit file paths in `git add`. Use a directory path only when every changed file under it belongs to that batch.

## Output format

````markdown
# Staging plan: <topic>

## Context
<2-5 sentences: branch, what the working tree holds, file counts (modified / untracked / deleted), which implementation plans the batches follow, why this order, which batches' tests wait on a later wiring batch.>

Run each block from the repo root, review with `git diff --staged`, then commit.

---

### 1. Schema, model and backfill command
<1-3 sentences on what this batch changes, from the diff. Name the source, e.g. "Plan 1, Task 1". Flag anything worth a closer look in review.>

```sh
git add \
  database/migrations/2026_11_12_154254_add_surname_to_characters_table.php \
  app/Models/Character.php \
  tests/Unit/Models/CharacterTest.php
```

```sh
git commit -m "feat: store character surnames in a separate column"
```

### 2. ...

---

## Already staged
## Left out
## Verification
- After the last batch, `git status --porcelain` prints only the Left out files (nothing, when there are none). `docs/` is git-ignored, so this plan never appears.
- <the test command to run before or between commits>
````

Omit **Already staged** and **Left out** when empty. Commit messages are one conventional-commit subject line (`feat:`, `fix:`, `refactor:`, `test:`, `chore:`, `docs:`), lower case, imperative, matching `git log`, describing the change rather than listing files.

## Verify coverage

Every file from step 1 appears in exactly one batch or in Left out / Already staged. Check it mechanically, not by eye: for each batch, pass its exact `git add` arguments to `git status --porcelain -uall -- <paths>` (this expands directory arguments), collect the results, and compare against the step 1 list. Report missing files, files in two batches, and paths in the plan that match nothing. State the result in your summary to the user (for example "188 of 188 files, no duplicates").

## Common mistakes

- Grouping by directory or file name without reading diffs.
- Batching by layer (all models, all tests) when a plan task cuts across layers.
- A leftover catch-all batch with a vague commit message.
- Plain `git status`, which hides files inside untracked directories.
- Routes or providers committed before the controllers they reference, including a shared file placed in the earlier of two batches.
- Reading only `git diff --stat` for modified files; the stat does not show which task a change belongs to.
- Claiming full coverage without running the check.
