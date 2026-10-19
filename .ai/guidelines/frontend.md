# Frontend (Inertia + React)

## Never define `broadcastAs()` on Notification classes

Never define `broadcastAs()` on a Laravel notification class (extends `Illuminate\Notifications\Notification`, implements `ShouldBroadcast`). Doing so renames the wire event, but `useEchoNotification` from `@laravel/echo-react` hardcodes listening for `.Illuminate\Notifications\Events\BroadcastNotificationCreated` and will never match — the frontend callback never fires.

To filter notifications on the frontend, use `broadcastType()` instead — it sets the `type` field that `useEchoNotification`'s third argument matches. `broadcastAs()` is fine on genuine broadcast **Event** classes (e.g. `BossKilled`, `EventAssignment`).
