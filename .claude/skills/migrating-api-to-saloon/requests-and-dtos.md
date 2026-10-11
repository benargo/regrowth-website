# Phase 3 — Build typed Requests + DTOs

One Request class per endpoint, each written **test-first**: the request/DTO test goes red before the class exists. Request tests use the Saloon-fake patterns in `testing-with-fakes.md` — read it alongside this file. Build resource by resource; callers still use the legacy service until Phase 4.

## Requests

Give each known resource type its own request (`GetItemMediaRequest`, `GetPlayableClassMediaRequest`) with its own cache TTL. Use a generic `GetMediaRequest(tag, id)`-style request only when the resource type really varies at runtime. Template: `Requests/Item/GetItemRequest.php`.

```php
class GetItemRequest extends Request implements Cacheable
{
    use HasCaching;

    protected Method $method = Method::GET;

    public function __construct(
        protected int $itemId,
        protected ?BlizzardNamespace $namespace = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/data/wow/item/{$this->itemId}";
    }

    public function boot(PendingRequest $pendingRequest): void
    {
        /** @var BlizzardConnector $connector */
        $connector = $pendingRequest->getConnector();

        $pendingRequest->headers()->add(
            'Battlenet-Namespace',
            ($this->namespace ?? BlizzardNamespace::default())->forStaticRequests($connector->getRegion()),
        );
    }

    public function cacheExpiryInSeconds(): int
    {
        return 2628000; // ~30 days
    }

    public function createDtoFromResponse(Response $response): ItemData
    {
        return ItemData::from($response->json());
    }
}
```

- Values the legacy method read implicitly from `config()` (realm, guild, server id) become **request constructor arguments**, never connector defaults.
- If the Phase 1 audit found retries on the legacy method: put `$tries`/`$retryInterval` on the **request** (only if it is idempotent, i.e. `GET`), never on the connector, and always override `handleRetry()` to return true only for `FatalRequestException` or a 5xx — Saloon otherwise retries 4xx too. Full example in `connector.md` ("Timeouts and retries").
- If the API is GraphQL or returns errors at HTTP 200 (`{"errors":[...]}`), `AlwaysThrowOnErrors` won't fire: detect the errors in `createDtoFromResponse()` (or a custom Response) and throw the typed exception there, with a test for it.
- Paginated endpoints: see `pagination.md`.

## DTOs

**DTOs use `spatie/laravel-data`**, not plain classes or hand-rolled `Arrayable` + `JsonSerializable`. New DTOs are `final class ... extends Data` with `readonly` promoted properties, `#[MapInputName(SnakeCaseMapper::class)]` for snake_case JSON, and `Optional|type` for keys the API may omit. Override `toArray()` only when the output shape must differ. Template: `Data/Item/ItemData.php`. The existing Blizzard DTOs predate the `final` rule, so add it on new ones.

**Custom Response class only when you need behaviour on the response** — e.g. memoising the DTO so middleware-enriched state survives repeated `dto()` calls (`Responses/GetMediaResponse.php` returns `$this->mediaData ??= MediaData::from($this->json())`). Most requests need no custom Response; returning the DTO via `createDtoFromResponse` is enough.

## DTO unit tests

**Every DTO must have a unit test.** Create it with `vendor/bin/sail artisan make:test --unit Http/Integrations/{Api}/Data/Thing/ThingDataTest --phpunit` (no `Unit/` prefix). Each test extends `Tests\TestCase` (no `RefreshDatabase`, no factories), carries the class-level domain group `#[Group('{api}-integration')]` from `.ai/rules/testing-groups.md` (don't invent new names), uses `#[Test]` with no `test_` prefix, and exercises hydration via `DtoClass::from($this->sampleApiResponse())`. Cover:

- **All required fields** — assert each property matches the expected cast value (use string inputs for integer/bool fields to prove the cast fires).
- **Casts** — if a field uses a custom or `BuiltinTypeCast`, assert the cast result (e.g. `'2'` → `2`, `'confirmed'` → `true`).
- **Nested collections** — assert `assertCount()` and `assertInstanceOf()` on each `#[DataCollectionOf]` property; spot-check a nested property.
- **Nullable / optional fields** — one test that omits them and asserts `null`; one test that populates them.
- **Empty collections** — for DTOs with collection properties, assert empty arrays are handled gracefully.

Place a private `sampleApiResponse(): array` helper at the bottom of the class (after all `#[Test]` methods) returning a realistic API payload with string values where the API sends strings. Classes with 11+ tests are split with `// ==================== label ====================` separators, and the helper block is labelled `helpers` (machine-enforced by `TestSuiteDocumentationStandardTest`).

**Exit criterion:** each request has its own test (per `testing-with-fakes.md`), every DTO it returns has a unit test, all green, no caller changed.

## Common mistakes

| Mistake | Reality / Fix |
|---|---|
| Plain DTO classes | Use `spatie/laravel-data`: `final class ... extends Data`, readonly promoted props, `MapInputName(SnakeCaseMapper)`. |
| Custom Response class for every request | Only when the response needs behaviour (e.g. DTO memoisation). Default: `createDtoFromResponse`. |
| One generic request for every resource type | Dedicated request per known resource type, each with its own cache TTL. Generic only when the type varies at runtime. |
| Request/DTO written before its failing test | Write the test first. |
