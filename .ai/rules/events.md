---
paths:
  - 'app/Events/**'
---

# Events

## Group related Events under a shared marker interface for one Listener to consume
When several distinct domain Events should trigger the same side effect (cache flush, export scheduling), define a marker interface in app/Contracts/Events/, have each Event implement it, and have a single Listener in app/Listeners/ type-hint the interface in handle() — do not register each Event/Listener pair manually in EventServiceProvider. Always dispatch via SomeEvent::dispatch(...), never the event() helper.
