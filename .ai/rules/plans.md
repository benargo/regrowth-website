---
paths:
  - 'docs/superpowers/plans/**'
---

# Plans

## Tick off plan checkboxes as tasks complete
When executing an implementation plan, change each step's `- [ ]` to `- [x]` in the plan file as soon as that step is completed. Do not leave checkboxes unticked until the end, and do not batch-tick steps that have not actually been done.

## Plans are ordered TDD red/green cycles
Implementation plans follow superpowers:test-driven-development as ordered RED → verify RED → GREEN → verify GREEN cycles, each naming the failing test, its expected failure, and the minimal code to pass. Never list production changes first with a trailing "add tests" section. A new Action's own test is its own red step before the controller test.

## Playwright-verify every page a wide frontend change touches
When a plan changes many pages (e.g. a prop shape change), its verification section lists every affected page under a Playwright step: visit each one (logged in via `/login/local`), take a snapshot and check console errors. A loose "check in the browser" is not enough.

## Ops plans justify every command and run SQL in Sequel Ace
In ops/deployment plans, every console command the operator runs gets a one-line "why" stating what goes wrong without it; convenience commands are labelled as such or cut. Prefer the least-resistance path without cutting safety corners. Every SQL query (including helper queries like row counts) is a Sequel Ace step, using CSV export + local diff when results must be compared; server shells only run non-SQL tools such as `mysqldump` and piping a dump into the client for import. Never `mysql -e`/`mariadb -e` or SQL in shell variables.
