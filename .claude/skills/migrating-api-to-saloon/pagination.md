# Pagination

Any endpoint that returns paginated results gets a dedicated paginator class in `Pagination/` — never an inline anonymous class on the connector. The **Blizzard** and **RaidHelper** integrations show two valid patterns.

## Directory convention

```
app/Http/Integrations/{Api}/
  Pagination/
    EventsPaginator.php       # extends PagedPaginator (header-based, RaidHelper)
    SearchPaginator.php       # extends Paginator (query-param, Blizzard)
```

## Two instantiation patterns

**Pattern A — request owns the paginator** (`HasRequestPagination`, Blizzard)

Use when a single request class is always paginated and the paginator is tightly coupled to it:

```php
// Request
class SearchItemsRequest extends Request implements Cacheable, HasRequestPagination, Paginatable
{
    public function paginate(Connector $connector): Paginator
    {
        return new SearchPaginator(connector: $connector, request: $this);
    }
}

// Caller
$paginator = (new SearchItemsRequest(name: 'Item'))->paginate($connector);
foreach ($paginator->items() as $item) { ... }
```

**Pattern B — caller instantiates the paginator directly** (RaidHelper)

Use when the connector serves multiple request types and you want to pick the paginator at the call site:

```php
$paginator = new EventsPaginator(connector: $connector, request: new GetEventsRequest($channelId));
foreach ($paginator->items() as $event) { ... }
```

## Implementing a paginator class

Extend `PagedPaginator` for page-number–based APIs (header or query param). Mirror this structure:

```php
class EventsPaginator extends PagedPaginator
{
    protected function isLastPage(Response $response): bool
    {
        // Return true when there are no more pages.
        // RaidHelper: stop when eventsTransmitted < 1000 (threshold, not a page count field).
        return (int) $response->json('eventsTransmitted', 0) < 1000;
    }

    /** @return array<int, mixed> */
    protected function getPageItems(Response $response, Request $request): array
    {
        return $response->json('postedEvents', []);
    }

    protected function applyPagination(Request $request): Request
    {
        // Header-based: add a Page header. Query-param-based: merge into query().
        $request->headers()->add('Page', $this->currentPage);
        return $request;
    }
}
```

## The `Paginatable` interface requirement

**The request passed to any paginator constructor must implement `Saloon\PaginationPlugin\Contracts\Paginatable`.** Without it, `Paginator::__construct()` throws `InvalidArgumentException` at runtime. For Pattern A this is already on the request class. For Pattern B, the request class needs it too, even if it has no `paginate()` method of its own.

## Testing paginators

Create `tests/Unit/Http/Integrations/{Api}/Pagination/{Name}PaginatorTest.php`. Tests to cover:

- **Multi-page iteration** — fake N pages, assert all `eventsTransmitted`/`page` values appear when iterating with `foreach`
- **`items()` across pages** — `iterator_to_array($paginator->items(), false)` gives all items in order
- **Single-page termination** — a response that signals last page stops after one request
- **Pagination transport** — assert the `Page` header (or query param) carries the correct page number

Use `Saloon::fake([...])` with sequential `MockResponse::make()` entries (no key = consumed in order):

```php
Saloon::fake([
    MockResponse::make(['eventsTransmitted' => 1000, 'postedEvents' => [...]], 200),
    MockResponse::make(['eventsTransmitted' => 3,    'postedEvents' => [...]], 200),
]);
```

The test's stub request must implement `Paginatable`:

```php
class EventsProbeRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;
    public function resolveEndpoint(): string { return '/probe'; }
}
```

**⚠️ Header values from `applyPagination` are integers, not strings.** `$request->headers()->add('Page', $this->currentPage)` stores an `int`. When asserting, use loose equality (`==`) not strict (`===`):

```php
Saloon::assertSent(function (Request $request, Response $response) {
    $page = $response->getPendingRequest()->headers()->get('Page');
    return $page == '1' || $page == '2'; // == not ===
});
```

(The `PendingRequest` caveat from `testing-with-fakes.md` applies: pagination headers are set by `applyPagination` during the send pipeline, so they live on the `PendingRequest`, not the base `Request`.)

## Common mistakes

| Mistake | Reality / Fix |
|---|---|
| Inline anonymous paginator on the connector | Extract to `Pagination/{Name}Paginator.php`. Inline classes can't be tested and block adding a second paginator later. |
| Forgetting `Paginatable` on the request | Paginator constructor throws `InvalidArgumentException` at runtime. All requests passed to a paginator must implement `Paginatable`. |
| Strict `===` comparison on paginator header values | `applyPagination` stores `$this->currentPage` as an `int`; `headers()->get()` returns it as-is. Use `==` when asserting page header values in tests. |
