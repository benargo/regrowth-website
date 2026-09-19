---
paths:
  - 'app/**/*.php'
---

# App

## Use UPPERCASE case names in enums
Backed enum cases should use UPPERCASE names (e.g. `case RETAIL = 'retail';`), not lowercase or StudlyCase. This applies to the case identifier only — the backing string value's casing is unaffected and should match whatever the external system/API expects.
