# Interview checklist

Ask only what discovery could not settle. Each question states what you found and offers your recommendation first. House decisions in `reference.md` are not questions.

## Always

1. **Fields.** For each column: editable or not, required, label, hint, input type (text, number, date, select from enum or model, colour, upload). Propose limits from the schema.
2. **Uniqueness.** Which field combination must be unique, and scoped to what (e.g. name + difficulty within a phase).
3. **Relationship steps.** Which relationships officers manage, in which wizard order, with which labels. For each one: link existing records (checklist), add new ones inline (`Relationships.AddRecord`), or reorder, and whether it also sets a flag on the linked records (e.g. `count_attendance`). No steps means no wizard.
4. **In use.** Propose `USAGE_RELATIONS` from the FK on-delete findings and confirm. Name every cascading link (pivot rows that would be silently removed) and every SET NULL FK (rows that would be orphaned), and ask whether each should block delete or be accepted.
5. **Index.** Card title, subtitle, details rows, banner class, sort order, intro sentence, empty-state icon and message.
6. **Dashboard card.** Title, one-line description, icon.

## When discovery shows it

- **Existing policy** (not `DatasetPolicy`): switch to `DatasetModel` + `DatasetPolicy`, or keep the policy and add the missing CRUD abilities?
- **Overhaul:** list every existing endpoint and page feature with your proposed home (details field, step, or retired). Confirm the mapping and any route renames. If an endpoint has side effects (an external API call, an upsert, a broadcast), ask whether it becomes an explicit action button, runs on save, or is dropped. If the existing pages are open to officers without `edit-datasets`, ask whether the new gate may narrow access or whether viewing stays on `view-officer-dashboard`.
- **Child model:** the parent select's grouping (e.g. phases grouped by game version), and whether moving to another parent is allowed (e.g. not across game versions when items are attached).
- **Sortable model:** does the parent's Edit page get a reorder step?
- **`HasMedia`:** which collection the upload writes to, accepted types and size, and whether removing the file is allowed.
- **Seeder overwrites edits:** confirm the create-only change, and whether a seeded record an officer deleted should come back on a re-run.
- **External matching by name or id:** block renames, warn on rename, or ignore?
- **Another dataset creates this model inline** (a `new_…` payload key): confirm retiring it in favour of links to this model's pages.
- **Side effects:** model events, broadcasts or caches that fire on save. Confirm they should fire from the management pages (and whether bulk saves should skip unchanged rows).
