---
paths:
  - 'resources/js/**/*.jsx'
---

# Js

## Never let React insert or remove a Font Awesome icon on its own
Font Awesome's autoReplaceSvg swaps each `<i>` rendered by `@/Components/FontAwesome/Icon` for an `<svg>` outside React. If React later inserts or removes that `<i>` as the topmost changed node, `removeChild`/`insertBefore` throws and the page goes blank.

- Bare icons: never conditionally mount an `<Icon>` itself (`{cond && <Icon/>}`, `cond ? <Icon a/> : <Icon b/>`). Always render it and toggle visibility with `cn(…, !isVisible && "hidden")`.
- Ternaries over subtrees: when both branches start with the same element types (e.g. `<div><Link>…` vs `<div><Link>…`), give each branch root a distinct `key` (e.g. `key="mobile-account-user"` / `key="mobile-account-guest"`) so React swaps the whole subtree instead of reusing the wrappers and removing icons one by one.
- Mounting or unmounting a whole subtree that contains icons (`{cond && <Link>…<Icon/>…</Link>}`, `<Can>`, branches with different root types) is safe.

## Abstract reusable UI into shared Components
New UI building blocks that could be used elsewhere (step lists, indicators, code chips, etc.) go in shared `resources/js/Components/` files with generic props. Keep a component page- or feature-local only when it genuinely can't be generalised (e.g. components mimicking a specific addon's UI, like GRM's `InGameButton`), and compose it from shared pieces. When an existing shared component nearly fits, use it, extending it with an optional prop if needed.
