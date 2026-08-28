# Third-Party API Service Classes

This project integrates with several external APIs (Blizzard, Discord, Warcraft Logs, Raid Helper). All service/connector classes for those APIs follow these rules.

## API services make HTTP calls only

Keep each API service class focused purely on making HTTP calls (optionally wrapped in `Cache::remember`). Do **not** add methods that:

- depend on another service,
- map responses into domain objects, or
- touch the database.

That work belongs to the consumer (job, controller, seeder). Example: a `getPlayableClassIconUrl()` method was rejected because it combined an API call with a `MediaService` resolution — consumers should call `getPlayableClassMedia()` and resolve URLs through `MediaService` themselves.

## Give each resource type its own dedicated method

When an API has a specific endpoint for a known resource type, give it its own method that calls that path directly (e.g. `getItemMedia`, `getPlayableClassMedia`). Dedicated methods keep their own cache keys, TTLs, and skip unnecessary validation.

Only use a generic `findMedia(tag, id)`-style method when the resource type genuinely varies at runtime.

## Inject config through the constructor

Pass config values into service classes through the constructor via the service provider. Do **not** call the `config()` helper inside the class. The class may expose a local `config(string $key)` helper using `Arr::get()` to read nested values, throwing if a required key is missing.

## Prefer dependency injection over facades

Use Laravel facades only as a last resort. Prefer constructor/method dependency injection wherever possible — it makes dependencies explicit and simplifies testing (real bindings + `Saloon::fake()` instead of facade-mocking).

- Controllers / Jobs / Commands / Notifications / Seeders → type-hint `BlizzardConnector` / `RenderConnector` in the constructor (or `handle()` for queue jobs) and let Laravel resolve them via the `BlizzardServiceProvider` singleton bindings.
- Where an existing helper exposes a facade (`Blizzard::send(...)`, `BlizzardAsset::...`), refactor the caller to receive the connector by DI and migrate the call to `$blizzard->send(...)`.
- Resources/Models cannot accept constructor injection — there `app(BlizzardConnector::class)` is acceptable, but call it out explicitly and minimise it (e.g. resolve once into a property).
- New code should never reach for `App\Facades\Blizzard` or `App\Facades\BlizzardAsset` unless DI is genuinely impossible.

## Catch the abstract `NotFoundException` in Blizzard batch loops

A 404 from the render CDN (`FetchAssetRequest` / `RenderConnector`) throws `App\Http\Integrations\Blizzard\Exceptions\MediaNotFoundException`. The item-data path (`GetItemRequest`) throws `ItemNotFoundException`. Both extend the abstract `App\Http\Integrations\Blizzard\Exceptions\NotFoundException`.

In seeders/resources that send both `GetItemRequest`/`GetItemMediaRequest` **and** `FetchAssetRequest`, catch the abstract parent so a single icon-CDN 404 doesn't abort the whole batch:

```php
catch (NotFoundException | BlizzardApiException | FatalRequestException $e) {
    // skip this record, continue the loop
}
```

Add a test that fakes a `FetchAssetRequest` 404 and asserts the loop continues with no media attached.
