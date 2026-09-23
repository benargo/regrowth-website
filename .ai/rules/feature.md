---
paths:
  - 'tests/Feature/**'
---

# Feature

## Array-style assertJson for API responses, AssertableJson reserved for Inertia
Assert JSON API endpoint responses with array-style ->assertJson([...])/assertJsonFragment. Reserve the fluent AssertableJson (fn (AssertableJson $page) => ...) for assertInertia() page-prop assertions only.
