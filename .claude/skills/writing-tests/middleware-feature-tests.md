# Middleware Feature Tests

Middleware that resolves routes/redirects (`redirect()->route(...)`, `$request->route()->getName()`) depends on the app's real, booted `RouteCollection`. That makes it a **Feature test**, not a Unit test, even though the test never goes through the HTTP kernel — `tests/Unit/**` is not allowed to depend on real routing infrastructure. Location: `tests/Feature/Middleware/{Name}Test.php`.

## Route-independent, database-independent

"Feature test" here means booted-app-dependent, not HTTP- or database-dependent. These tests never dispatch a real route (`handle()` is called directly — see below) and never touch the database (models are `->make()`d, never `->create()`d — see "Model creation" below). Don't add `RefreshDatabase` or similar traits to these test classes; there's nothing in them that persists.

## Call `handle()` directly — every test case, no exceptions

Never drive the middleware through `$this->get(...)`, `$this->post(...)`, or any other full HTTP call, and never register an ad-hoc route in `setUp()` to dispatch through the kernel. This holds even for "regression" or "it doesn't crash" cases that feel like they need the real request lifecycle — they don't; build the `Request` (and its referer, session, or route mock) by hand and call `handle()` on it like every other case. The canonical shape, per [A Guide to Testing Middleware in Laravel](https://dev.to/sergunik/a-guide-to-testing-middleware-in-laravel-1jna):

```php
#[Test]
public function it_does_something(): void
{
    $request = new Request();

    $next = function () {
        return response('ok');
    };

    $middleware = new MiddlewareClassBeingTested();
    $response = $middleware->handle($request, $next);

    $this->assertEquals(/* ... */);
}
```

Adapt `$request` (headers, session, route mock) and the `$next` closure's return per case; the `new Middleware()->handle($request, $next)` call itself doesn't change.

Reaching for `$this->get(...)` "just this once" also hits a real Laravel quirk: registering a route ad-hoc in `setUp()` and dispatching through the kernel looks natural but `Route::get(...)->name(...)` only indexes the route into `RouteCollection`'s name lookup if the route already has a name _at the moment `get()` registers it_ — a route named via a chained `->name()` call late in a test's `setUp()` can 404 on `route()`/`Route::has()` even though it dispatches fine. Every route in `routes/web.php` uses this same chaining and works, because those are reconciled once at boot — a single ad-hoc mid-test route doesn't get that treatment. Sidestep the whole problem: call `(new Middleware)->handle($request, $next)` directly and assert on the returned `Response`.

## Mock `Illuminate\Routing\Route`, wired via `setRouteResolver()` — never mock `Request` itself

```php
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Mockery;
use Mockery\MockInterface;

private function requestForItem(Item $item, ?string $slug): Request
{
    /** @var Route&MockInterface $route */
    $route = Mockery::mock(Route::class);
    $route->shouldReceive('parameter')->with('item', null)->andReturn($item);
    $route->shouldReceive('parameter')->with('slug', null)->andReturn($slug);
    $route->shouldReceive('getName')->andReturn('test-route');

    $request = Request::create('/test-route');
    $request->setRouteResolver(fn () => $route);

    return $request;
}
```

## Mockery gotcha

`->with()` matches on exact arity. `$request->route('item')` internally calls `Route::parameter('item', $default)` with **two** positional arguments (`$default` defaults to `null`), not one. A stub declared `->with('item')` will never match and throws `NoMatchingExpectationException` — declare `->with('item', null)` explicitly.

## If the middleware redirects via a named route

(`redirect()->route($name, ...)`), that name must resolve against a real route or `route()` throws `RouteNotFoundException` — the mock alone isn't enough. Add one generic route inside the `testing`-environment-gated block in `routes/testing.php` (registered at normal boot time, so it's fully indexed):

```php
if (app()->environment('testing')) {
    Route::get('/test-route', fn () => response('ok'))->name('test-route');
}
```

Every test case's mocked `getName()` can return this same `'test-route'` name — the middleware only cares about the _name_ it gets back from `$request->route()->getName()`, never the target route's own URI or parameters.

If the middleware under test never redirects via a named route, don't add a route to `routes/testing.php` — no route is needed at all, unless a mocked `Route` has already been identified as needed for another purpose. Adding an unused route is dead weight.

## Model creation

Use `Item::factory()->make(...)`, never `->create()` — the middleware never touches the database, so there's no reason to persist. Persisting anyway is a smell that the test has drifted from what's actually being exercised; if a case seems to need a saved row, that's a sign the assertion belongs in a different test, not a reason to add `RefreshDatabase`.
