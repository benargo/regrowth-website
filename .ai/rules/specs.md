---
paths:
  - 'docs/superpowers/specs/**'
  - 'docs/superpowers/plans/**'
---

# Specs

## Spec before plan; archive implemented docs to /.archives
Once a design is agreed, write the spec at `docs/superpowers/specs/YYYY-MM-DD-<topic>-design.md` (Date/Skills/Status header, Goal, Current state, numbered Decisions table, per-area sections, Testing table, Open questions) and wait for review before writing the plan. Implemented specs and plans move with plain `mv` (not `git mv`) to the git-ignored `/.archives/specs/` and `/.archives/plans/` (no `superpowers` segment). Superseded, never-implemented plans are deleted, not archived.
