# Testing with Saloon fakes

Used from Phase 3 onward: request tests (Phase 3), caller tests (Phase 4), and the bulk conversion of legacy Mockery tests (Phase 5).

Phase 5 is where the biggest, most error-prone change happens: replace service-layer Mockery with HTTP-layer Saloon fakes (`Saloon::fake()` is the Laravel-plugin alias of `MockClient::global()`). Key the fake by **request class** (preferred) or URL wildcard.

`tests/TestCase.php` calls `Saloon\Config::preventStrayRequests()` once, so any request without a matching fake throws instead of reaching the network. Don't add per-test guards, and don't reach for `Http::fake()`/`Http::preventStrayRequests()`, because they don't cover Saloon.

## ⚠️ The #1 gotcha (OAuth APIs only): mock the token request

**Every fake that sends an authenticated request MUST mock the OAuth token request first.** Without it, the connector's `boot()` token fetch throws a stray-request / "unable to guess a mock response" error. If the caller catches exceptions, that error disappears and the only symptom is empty tables. Static-token APIs (`TokenAuthenticator`) need no token mock.

```php
use Saloon\Laravel\Facades\Saloon;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\OAuth2\GetClientCredentialsTokenBasicAuthRequest;

Saloon::fake([
    // ALWAYS include the token mock for authenticated APIs:
    GetClientCredentialsTokenBasicAuthRequest::class => MockResponse::make([
        'access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600,
    ]),
    // (URL form also works: 'eu.battle.net/oauth/token' => ...)

    GetCharacterProfileRequest::class => MockResponse::make($this->makeProfileResponse(), 200),
]);
```

## Dynamic responses

When one request class is hit with different ids, use a callable that extracts the id from the URL:

```php
GetItemRequest::class => function (PendingRequest $request): MockResponse {
    $id = (int) last(explode('/', parse_url($request->getUrl(), PHP_URL_PATH)));
    if ($id === 28453) {
        return MockResponse::make(['code' => 404, 'type' => 'BLZWEBAPI00000404'], 404);
    }
    return MockResponse::make($this->makeItemResponse($id), 200);
},
```

## Assertions

`Saloon::assertSent(GetItemRequest::class)`, `Saloon::assertSentCount(2)`, `Saloon::assertNothingSent()` (great for "skipped because already populated" paths).

**⚠️ Asserting on auth / boot-applied headers: read the `PendingRequest`, not the `Request`.** The `assertSent` closure is called as `$closure($request, $response)` where `$request` is the **base `Request`** (`MockClient::checkClosureAgainstResponses`). Authentication (`defaultAuth()`) and anything added in a request's `boot()` are applied during the send pipeline to the **`PendingRequest`** and never propagate back to the base `Request`. So `$request->headers()->get('Authorization')` is always `null`. To assert on what was actually sent, reach the sent `PendingRequest` through the `Response`:

```php
Saloon::assertSent(function (Request $request, Response $response) {
    // ❌ $request->headers()->get('Authorization') is null — auth isn't on the base Request
    return $response->getPendingRequest()->headers()->get('Authorization') === 'test-token';
});
```

`$request->body()`, `$request->resolveEndpoint()`, and `$request instanceof X` *are* fine on the base `Request` — this only bites for state the pipeline adds (auth headers, `boot()` headers/query, pagination headers).

## Testing exception translation

Fake an API-shaped error body + status and assert the typed exception (`tests/Unit/Http/Integrations/Blizzard/BlizzardConnectorTest.php`):

```php
Saloon::fake([
    'eu.battle.net/oauth/token' => $this->tokenMock(),
    'eu.api.blizzard.com/profile/wow/character/*' =>
        MockResponse::make(['type' => 'BLZWEBAPI00000404', 'detail' => 'Not found'], 404),
]);
$this->expectException(CharacterNotFoundException::class);
$this->makeConnector()->send(/* ... */);
```

**Error-path tests assert the outcome; they never swallow it.** Use `$this->expectException(TypedException::class)` when the caller should propagate, or assert the handled result (row not written, job released, log written) when the caller catches. `try { ... } catch (\Throwable) {}` in a test hides the very failure you are testing.

## Test setup conventions

- Resolve the real connector binding (`$this->app->make({Api}Connector::class)`, or let Laravel inject it) and fake at the HTTP layer. Don't Mockery-mock the connector or requests.
- Use `createStub()` rather than `createMock()` for any collaborator that has no expectations. Run with `--display-phpunit-notices` and fix every notice.
- Provide a base test case (`BlizzardTestCase`) with `makeConnector()` and `tokenMock()` helpers so every integration test shares them.
- Every test class carries the class-level domain group from `.ai/rules/testing-groups.md` (e.g. `#[Group('blizzard-integration')]`); don't invent names.
- Per project rules, convert/repoint legacy tests — don't delete test files without approval.

**Phase 5 exit criterion:** no test mocks the legacy service; all integration tests fake at the HTTP layer and pass.

## Common mistakes

| Mistake | Reality / Fix |
|---|---|
| Forgetting the OAuth token mock in tests | Whole request chain fails silently; tables stay empty. Always mock `GetClientCredentialsTokenBasicAuthRequest` (or the token URL) first. |
| `try { ... } catch (\Throwable) {}` in an error-path test | `expectException(...)`, or assert the handled outcome. |
| Asserting auth header off the `assertSent` `Request` arg | It's the base `Request`; auth/`boot()` headers live on the sent `PendingRequest`. Read `$response->getPendingRequest()->headers()->get(...)` instead. |
| `Http::fake()` / `Http::preventStrayRequests()` | They don't cover Saloon. Use `Saloon::fake()`; stray requests are already prevented in `tests/TestCase.php`. |
| Mockery-mocking the connector or a request | Resolve the real binding and fake at the HTTP layer. |
| Test class without a `#[Group]` / invented group name | Class-level domain group from `testing-groups.md`, e.g. `blizzard-integration`. |
| Deleting legacy tests to "clean up" | Don't delete test files without approval — convert/repoint them. |
