# Frontend (Inertia + React)

## Search shared components before writing new front-end code

Before proposing or implementing any front-end change, search `resources/js/Components/`, `resources/js/Helpers/`, and `resources/js/Hooks/` for existing shared components, helpers, and hooks, and reuse them. The project has a rich shared library; duplicating it creates inconsistency and maintenance burden.

## Render from props, not `useState`, on pages that call `router.reload()`

In read-only Inertia pages that call `router.reload()` in response to broadcast events, render directly from the incoming props — do **not** copy them into `useState`.

`useState` initialises once at mount and ignores later prop changes, so when `router.reload()` delivers fresh props the component re-renders but the state stays frozen and the UI never updates. Render from the prop directly (e.g. `event.composition?.groups`). Only use `useState` for data that needs client-side-only mutation (optimistic updates, UI toggles) that won't be reconciled via a reload.

## Never define `broadcastAs()` on Notification classes

Never define `broadcastAs()` on a Laravel notification class (extends `Illuminate\Notifications\Notification`, implements `ShouldBroadcast`). Doing so renames the wire event, but `useEchoNotification` from `@laravel/echo-react` hardcodes listening for `.Illuminate\Notifications\Events\BroadcastNotificationCreated` and will never match — the frontend callback never fires.

To filter notifications on the frontend, use `broadcastType()` instead — it sets the `type` field that `useEchoNotification`'s third argument matches. `broadcastAs()` is fine on genuine broadcast **Event** classes (e.g. `BossKilled`, `EventAssignment`).

## Naming conventions

- **Components**: PascalCase (`LoginForm`).
- **Component files**: PascalCase (`LoginForm.jsx`). Non-component files (hooks, helpers, utils): camelCase (`useSyncedSelection.jsx`, `formatDate.js`). Folders: camelCase.
- **Hooks**: camelCase, prefixed with `use` (`useSyncedSelection`).
- **Functions**: camelCase (`getUsers`).
- **Variables**: camelCase. Prefer descriptive names over vague ones (`data`, `value`); spell out abbreviations (`imageButton`, not `imgBtn`) except well-known acronyms (`baseURL`, `userAPI`).
- **Constants**: SCREAMING_SNAKE_CASE only for values fixed before any component runs — module-level literals such as config, enum-like maps, or regexes (`const DATE_FORMAT = "YYYY-MM-DD"`). A `const` declared inside a component or function body — even if it never changes — is a runtime value, not a compile-time constant, and takes camelCase (`const userList = []`, `const segmentShape = "..."`).
- **Booleans**: prefix with `is`/`has`/`should` (`isLoading`, `hasError`).
