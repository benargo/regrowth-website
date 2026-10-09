# Dataset CRUD reference

## House decisions (already made, so don't ask again)

| Topic | Decision |
|---|---|
| Controller | Root namespace `App\Http\Controllers\{Model}Controller`, plain multi-method (not `Route::resource`). Class `#[Authorize('view-officer-dashboard')]`, each method `#[Authorize('viewAny'\|'create', Model::class)]` or `#[Authorize('update'\|'delete', '{param}')]` |
| Routes | In the `manage` group in `routes/web.php`: `/manage/{kebab-plural}`, names `management.{kebab-plural}.{index,create,store,edit,setup,review,update,destroy}`. `update` is PATCH and partial |
| Requests | `app/Http/Requests/Store{Model}Request.php`, and `Update{Model}Request` extending it with every key `sometimes` and using `ChecksEditLock` |
| Tests | Create files with `vendor/bin/sail artisan make:test --phpunit {Path}Test --no-interaction` (add `--unit` for unit tests). Controller: `tests/Feature/Dashboard/{Model}ControllerTest.php` extending `Tests\Support\DashboardTestCase`. Actions: `tests/Feature/Actions/{Model}/…Test.php`, one per Action |
| React | Pages `resources/js/Pages/Manage/{Plural}/{Index,Create,Edit,Setup,Review}.jsx`. Model components `resources/js/Components/{Plural}/` (`{Model}Form.jsx` + `{model}FormData()`, `{StepStudly}Section.jsx` per step) |
| Wizard | Only when the model has relationship steps: `store` → first setup step → … → Review. Without steps: no Setup/Review routes or pages, `setupSteps()` returns `null`, and `store` redirects to Edit |
| Edit lock | Always, on every Edit (and Setup) page |
| Delete | Blocked while in use: `TracksUsage` + `USAGE_RELATIONS`, which always includes every RESTRICT FK. Refused while another officer holds the lock. Cascading pivot links don't block by default (GameVersion's races and classes). The interview names each one so the user can decide to count it instead. A SET NULL FK doesn't block either, but deleting orphans those rows (e.g. raids lose their phase), so the interview names each one too |
| Policy | `DatasetModel` + `#[UsePolicy(DatasetPolicy::class)]` (`edit-datasets`) by default. If the model already has its own policy, ask |
| Child models | Own CRUD. The parent is a select on the details form. Index cards grouped by parent (one `IndexLayout` section per parent, in parent order). A nullable parent FK adds a final orphans group (e.g. "No phase"). Sibling order is a step (or field) on the **parent's** Edit page |
| Media | `HasMedia` models always get an upload field on the details form |
| Seeders | Stay as fresh-install bootstrap. Re-running them must not overwrite officer edits. If a seeder uses `updateOrCreate`/`upsert` on editable columns, the plan includes a task (with a seeder test) to make it create-only for existing rows |
| Overhaul | Move the controller to the root namespace and rename non-standard routes (e.g. `phases.view` → `phases.index`), updating every caller. Fold each bespoke endpoint into a details field or a step through `update`. Retire the old endpoints in `routes/deprecated.php`, in the routes task when a new route reuses an old path or name (otherwise the routes collide), else in the retirement task: GET pages redirect to the new route, writes `abort(410)`. Each one gets a test in `tests/Feature/DeprecatedRoutesTest.php` with `#[Group('deprecated')]`. Delete the superseded requests, page and tests. Folding an endpoint must not narrow access: if officers with only `view-officer-dashboard` could use it before, the interview asks before the plan gates it behind `edit-datasets` |
| Retired inline creation | If another dataset's `update` creates this model through a payload key (e.g. GameVersion's `new_raid`), retire the key once this model has its own CRUD. Remove its rules and action branch, add a test that a stale page sending it is ignored (precedent: `it_ignores_guild_tag_ids_sent_by_a_stale_page`), and replace the inline form with links to this model's Create and Index pages. The tests for the retired key are deleted or rewritten. This is the one case where GameVersion's tests change; extraction tasks leave them untouched. The job still counts as New |
| Resource | Add `forManagement()` / `collectionForManagement()` to the model's existing resource (see `GameVersionResource`). The default shape stays byte-identical, pinned by an exact-keys test, even where it breaks a newer convention (e.g. returns `->value`). New conventions apply inside the management scope only |
| Composite uniqueness | Autosave sends only the changed field. A rule over several columns (e.g. name + difficulty + phase) has to fill missing keys from the stored record, e.g. in the request's `after()`. A plain `Rule::unique` on one field misses a change to the others. Pin it with a test that changes only the second column |
| Shared components | When the model needs something a `Datasets/*` or `FormControls` component lacks (grouped Index sections, option groups in `OptionSelect`, a heading level on `RecordCard`), add it there as an optional prop that leaves GameVersion's output unchanged. Don't fork the component |
| Enum options | Use the enum's `label()` when it has one, otherwise `Str::ucfirst($value)`. Add a `label()` when the values aren't readable (e.g. CSS classes) |
| Attributes on linked records | A step that links records and also sets a flag on them (e.g. which raids count for attendance) sends a second id list (`{relation}_ids` + `{flag}_ids`). Autosave sends each list on its own, so no rule may require both together. The action checks each with `Arr::exists`, and a flag id outside the linked ids is a validation error |
| Update action | `Update{Model}` wraps its writes in `DB::transaction` only when it makes more than one write. Details-only models get a single `update()` |
| Dashboard | A `DashboardCard` in the "Datasets" `Collapsible` of `Pages/Manage/Dashboard.jsx`, wrapped in `<Can permission="…">` matching the policy |
| Shared additions | A task that adds a shared piece (a contract, an optional component prop, a new Action) adds its row to § Shared layer in this file, in the same task |
| Conflicting conventions | `.ai/rules/*` wins over memory and older plans. Where the rules conflict with the surrounding files (e.g. unit tests that use `RefreshDatabase`), follow the rules for new files and the existing file's style when editing it, and note it in Decisions |
| Git and DB | No commit, stage, branch or migration-run steps. Migrations are written, never run |

## Shared layer: reuse these

| Piece | Use |
|---|---|
| `App\Contracts\Models\DatasetModel` + `App\Policies\DatasetPolicy` | Authorisation |
| `App\Contracts\Models\EditLockable` + `App\Models\Concerns\HasEditLock` | `acquireEditLock`, `isLockedForEditingBy`, `isBeingEdited`, `editor`. Cache keys `{table-with-hyphens}.{id}.*` |
| `App\Models\Concerns\TracksUsage` | `isInUse()`, `withUsageCounts()` scope. Reads `static::USAGE_RELATIONS` |
| `App\Contracts\Datasets\SetupStep` + `App\Enums\Concerns\IsSetupStep` | `{Model}SetupStep` enum: cases + `label()` only |
| `App\Actions\Datasets\BuildDatasetRoutes` | `Build{Model}Routes` subclass: `routePrefix()`, `setupSteps()` |
| `App\Actions\Datasets\ResolveEditLock` | `...ResolveEditLock::run($request, $record)` gives `canEdit` + `editor` props (honours `X-Edit-Idle`) |
| `App\Http\Requests\Concerns\ChecksEditLock` | `after()` returns `[$this->editLockCheck('{param}', '{record name}')]` |
| `resources/js/Components/Datasets/*` | `IndexLayout` (`addHref` optional: no Add link without it), `RecordCard` + `usageSummary` (optional `editLabel`/`editIcon`; `usage` and `onDelete` optional, hiding the usage sentence and Delete button), `DetailsForm`, `EditLayout` (`.Details`, `.Steps`), `EditLockGuard`, `SetupSteps`, `SetupWizardStep`, `SetupNavigation`, `Relationships` (`.AddRecord`, `.Step`), `RecordChecklist`, `ReviewSummary`, `ReviewSection` + `selectedOptions` |
| `resources/js/Hooks/*` | `useRecordDeletion`, `useRelationshipForm` (+ `AUTOSAVE_DELAY`), `useNewRecordForm`, `useSyncedSelection`, `useAutosave` (`useFormAutosave`, `autosaveVisit`, `AutosaveProvider`) |
| `resources/js/Components/FormControls.jsx` | `FormRow`, `FormSection`, `OptionSelect`, `SaveButton`, `ErrorSummary`, `firstError`, class names |

Per-model by design (copy the shape, not the code): `Build{Model}Relationships` (returns `{data, selected_ids}` groups), `Update{Model}` (partial update in `DB::transaction`, `Arr::exists` per relationship key), the step section components, and `Review.jsx`'s `STEP_SUMMARIES`.

## Not yet extracted (extract on first reuse, then move this row up)

| GameVersion-specific code | Extract to |
|---|---|
| `GameVersionController::destroy` lock + in-use guard and flash messages | `App\Actions\Datasets\DeleteDatasetRecord::handle(Request, Model&EditLockable&HasUsage, string $recordName, string $indexRoute): RedirectResponse`. Needs a `App\Contracts\Models\HasUsage` contract (`isInUse()`) implemented through `TracksUsage`, since a trait can't be type-hinted. The record's display name comes from the caller for the flash message. An optional `afterDestroy` callback covers cleanup (cache flush, media). Returning a redirect from a shared Action is accepted here. Tests: refused while locked, refused while in use, deletes and flashes |
| `store()` redirect to `management.game-versions.setup` + `GameVersionSetupStep::first()` | `BuildDatasetRoutes::afterStore(Model)` (first setup step, or Edit without a wizard) |
| `formOptions()` / `enumOptions()` private controller helpers | e.g. `App\Actions\Datasets\BuildEnumOptions`, preferring each enum's `label()` |
| `BuildDatasetRoutes::forEdit()` always builds the `review` URL, which throws for a dataset without a wizard | Include `review` only when `setupSteps()` isn't null. Test both cases in `BuildDatasetRoutesTest` |
| `GuildRanks/RankLadder.jsx` drag-and-drop ordering | `Datasets/SortableList.jsx`, when a second ordered list (e.g. bosses in a raid) needs it |

When a plan extracts one of these, the same task updates this file.

## Discovery checklist

- Model: fillable, casts, accessors, traits (`HasMedia`, `Sortable` + `buildSortQuery`, `BroadcastsEvents`, cache-flushing concerns), `$dispatchesEvents`, observers, existing policy.
- Schema (Boost `database-schema`): column types and lengths (they set validation limits), nullability, DB enums, unique indexes, and **every FK's on-delete** pointing at this table (RESTRICT means it must count as usage; CASCADE means it silently deletes, so it must count as usage or the user must accept it; SET NULL orphans rows, so the user must accept it).
- Factory states. Seeders that write this model, and whether a re-run overwrites edits.
- Existing controller, routes (`vendor/bin/sail artisan route:list --name=…`), form requests, pages, tests, dashboard card.
- The resource and every consumer of its default shape.
- Bespoke endpoints and deferred props with side effects (external API calls, upserts, broadcasts), which can't simply become an autosaved field.
- Code that matches records by id or name (external syncs, e.g. Raid-Helper's `SyncEvent`), which constrains renames and id changes.
- Parent and child datasets, and whether they are managed already.
