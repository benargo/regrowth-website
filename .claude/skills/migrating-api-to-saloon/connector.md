# Phase 2 — Scaffold the connector, auth, and exceptions

Create `app/Http/Integrations/{Api}/` mirroring Blizzard:

```
app/Http/Integrations/{Api}/
  {Api}Connector.php          # extends Saloon\Http\Connector
  Region.php / GameVersion.php # enums for host/namespace variants (if the API has them)
  Requests/{Resource}/...      # one Request class per endpoint
  Responses/...                # custom Response classes only when needed (see requests-and-dtos.md)
  Data/{Resource}/...          # spatie/laravel-data DTOs
  Exceptions/...               # typed hierarchy + marker interface
  Concerns/HasCaching.php      # Cacheable plumbing (copy from Blizzard)
  Pagination/...               # paginator classes (see pagination.md)
```

Generate Saloon classes with the Artisan generators, then move/adjust — don't hand-write them (check `--help` for argument order):

```bash
vendor/bin/sail artisan saloon:connector {Api} {Api}Connector --no-interaction
vendor/bin/sail artisan saloon:request {Api} GetThingRequest --no-interaction
vendor/bin/sail artisan make:class Http/Integrations/{Api}/Data/Thing/ThingData --no-interaction
```

New enums (regions, namespaces, versions) use **PascalCase** case names (`case Europe = 'eu';`). Existing Blizzard enums such as `Region::EU` predate the rule — leave them.

## Connector

`app/Http/Integrations/Blizzard/BlizzardConnector.php` is the template:
- Constructor takes config as **promoted properties** (clientId, secret, region/host enum, locale) — injected by the service provider, never read via `config()` inside the connector. Validate invariants in the constructor and throw early (see `BlizzardConnector::__construct` rejecting an unsupported locale).
- Connector and requests **only make HTTP calls**. No database access, no other services, no mapping into Eloquent/domain objects — that belongs in the consuming job/controller/seeder.
- `resolveBaseUrl()` returns the host (often region/version-derived).
- Traits: `AcceptsJson`, `AlwaysThrowOnErrors`, plus `HasRateLimits`, `HasTimeout` and OAuth grant traits as needed.
- `getRequestException(Response, ?Throwable)` translates upstream errors into the typed hierarchy.
- If the API uses a static token (no OAuth), use `defaultAuth()` returning a `TokenAuthenticator` (see `RaidHelperConnector`). The OAuth `boot()` and token-mock steps then do not apply.

## Timeouts and retries

Carry the legacy client's behaviour (captured in the Phase 1 audit) over deliberately (`laravel-best-practices` `rules/http-client.md`):
- Timeouts need the `HasTimeout` trait. Declaring `$connectTimeout`/`$requestTimeout` without it does nothing.
- Retry **only idempotent requests**. Put `$tries`/`$retryInterval`/`$useExponentialBackoff` on the individual `GET` request classes, not on the connector. A connector-level `$tries` also retries every `POST`/`PUT`/`DELETE`. Retry a state-changing request only if the API supports an idempotency key.
- Don't add retries for 429s when `HasRateLimits` is in use; the rate-limit plugin owns those.

```php
class DiscordConnector extends Connector
{
    use AcceptsJson;
    use AlwaysThrowOnErrors;
    use HasTimeout;

    protected int $connectTimeout = 3;

    protected int $requestTimeout = 10;
}

class GetGuildMemberRequest extends Request
{
    public ?int $tries = 3;

    public ?int $retryInterval = 200; // milliseconds

    public function handleRetry(FatalRequestException|RequestException $exception, Request $request): bool
    {
        return $exception instanceof FatalRequestException
            || $exception->getResponse()->serverError();
    }
}
```

Saloon retries **every** failed response by default, 4xx included, so without `handleRetry()` a 404 costs `$tries` round-trips plus the sleeps. Override it to retry only connection failures and 5xx responses.

## OAuth (client-credentials APIs only)

Copy Blizzard's pattern exactly — `ClientCredentialsBasicAuthGrant` trait, `defaultOauthConfig()` with `setAllowBaseUrlOverride(true)->setTokenEndpoint(...)` (the token host usually differs from the API host), and a `boot()` that skips authentication when the pending request **is** the token request (else infinite loop), caching the token in `Cache::tags(['{api}', 'api-auth'])`.

Every test that sends an authenticated request must then mock the token request — see `testing-with-fakes.md`.

## Exceptions — the bridge that keeps callers' `catch` blocks working

- A marker interface `{Api}RequestException extends Throwable` (see `Exceptions/BlizzardRequestException.php`) with accessors like `getMethod()/getEndpoint()/getBlizzardStatus()/getBlizzardCode()/getBlizzardBody()`.
- A generic `ApiException extends Saloon\Exceptions\Request\ClientException implements {Api}RequestException` as the fallback.
- An **abstract** per-status parent (`Exceptions/NotFoundException.php` extends Saloon's `Statuses\NotFoundException` and implements the marker interface). Resource-specific subclasses (`CharacterNotFoundException`, `ItemNotFoundException`, `MediaNotFoundException`) extend that parent. Callers that loop over several request types catch the abstract parent, so one 404 doesn't abort the batch:

```php
foreach ($itemIds as $itemId) {
    try {
        // GetItemRequest + FetchAssetRequest ...
    } catch (NotFoundException|ApiException|FatalRequestException) {
        continue;
    }
}
```

Each such loop gets a test that fakes a 404 for one record and asserts the loop carries on.

- During the phased cutover it is normal and correct for the connector to throw **new** typed exceptions while callers still `catch` the **legacy** interface — make the new exceptions implement an interface the old `catch` accepts, or update the `catch` when you migrate that caller. Don't agonise over it; bridge via the interface.
- **GraphQL / 200-with-errors APIs:** `AlwaysThrowOnErrors` only fires on HTTP failure status. Detect `{"errors":[...]}` at HTTP 200 in the response/DTO layer, not in `getRequestException`.

Test the translation per `testing-with-fakes.md` ("Testing exception translation").

## Register the connector

Register it as a singleton in the API's service provider, **alongside the still-live legacy bindings**:

```php
// app/Providers/{Api}ServiceProvider.php — register()
$config = config('services.{api}');

$this->app->singleton({Api}Connector::class, function (Application $app) use ($config) {
    return new {Api}Connector(
        clientId: data_get($config, 'client_id'),
        clientSecret: data_get($config, 'client_secret'),
        region: Region::from(data_get($config, 'region', 'eu')),
    );
});
```

Read config with the `config()` helper in the provider, never `env()`. Throw for a required key that is missing (`data_get(...) ?? throw new RuntimeException('services.{api}.x is not configured.')`). Add the connector to `provides()`.

**Do not add a facade.** Callers receive the connector by dependency injection (Phase 4). `App\Facades\Blizzard`/`BlizzardAsset` are legacy and are being migrated away from. Don't copy them.

**Exit criterion:** connector authenticates, exceptions exist, **no caller has changed**, app is green.

## Common mistakes

| Mistake | Reality / Fix |
|---|---|
| Reading `config()` inside the connector | Inject config via constructor (promoted props) from the service provider. Connector is config-agnostic. |
| `$requestTimeout` without `HasTimeout` | The property is ignored. Add the trait. |
| `$tries` on the connector | It retries non-idempotent writes too. Put retries on `GET` request classes. |
| `$tries` without `handleRetry()` | Saloon retries 404s/422s too. Override `handleRetry()` to retry only fatal errors and 5xx. |
| Connector/request touching the DB, another service, or building Eloquent models | HTTP calls only. Mapping and persistence go in the caller (`updateOrCreate` on the external id). |
| Defaulting realm/guild/server ids on the connector | Pass them as request constructor arguments. |
| Catching only the leaf exception in a mixed-request batch loop | Catch the abstract parent (`NotFoundException`) so one 404 doesn't abort the batch. |
| OAuth `boot()` that authenticates the token request itself | Infinite loop. Skip auth when the pending request is the token request. |
