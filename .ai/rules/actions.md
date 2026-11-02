---
paths:
  - 'app/Actions/**'
  - 'tests/Feature/Actions/**'
---

# Actions

## One Action class, one task
Each Laravel Action (lorisleiva/laravel-actions) does exactly one task through `handle()`. Don't expose several public helper methods on an Action, and don't hide a real task inside a private method or a plain support class. Give each operation a consumer calls, and each shared internal step worth faking, its own Action. Pure, stateless helpers go to their natural home (an enum method, a model method).

## Every Action gets its own feature test
Each new `app/Actions/{Domain}/{Action}.php` gets a dedicated test at `tests/Feature/Actions/{Domain}/{Action}Test.php` covering its edge cases directly. Controller tests that call the Action only assert its result is wired through to the response/props.
