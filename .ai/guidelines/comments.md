# Comments & Docblocks

## Describe *what*, not *why*

Comments and docblocks (PHP and JS alike) describe **what** the code does — its behaviour, inputs, outputs, and side effects. Do not explain **why** it was written that way: design rationale, rejected alternatives, bug history, and workarounds belong in the commit message or PR description, not the source.

```php
// Wrong — explains the reasoning
/**
 * We cache this for an hour because the Blizzard API rate-limits us
 * and the data rarely changes.
 */

// Right — describes the behaviour
/**
 * Fetch the playable class media, cached for one hour.
 */
```
