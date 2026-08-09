# Middleware Feature Tests

Middleware that resolves routes/redirects (`redirect()->route(...)`, `$request->route()->getName()`) depends on the app's real, booted `RouteCollection`. That makes it a **Feature test**, not a Unit test, even though the test never goes through the HTTP kernel — `tests/Unit/**` is not allowed to depend on real routing infrastructure. Location: `tests/Feature/Middleware/{Name}Test.php`.

**Call `handle()` directly.** Don't drive the middleware through `$this->get(...)`. Registering an ad-hoc route in `setUp()` and dispatching through the kernel looks natural but hits a real Laravel quirk: `Route::get(...)->name(...)` only indexes the route into `RouteCollection`'s name lookup if the route already has a name *at the moment `get()` registers it* — a route named via a chained `->name()` call late in a test's `setUp()` can 404 on `route()`/`Route::has()` even though it dispatches fine. Every route in `routes/web.php` uses this same chaining and works, because those are reconciled once at boot — a single ad-hoc mid-test route doesn't get that treatment. Sidestep the whole problem: call `(new Middleware)->handle($request, $next)` directly and assert on the returned `Response`.

**Mock `Illuminate\Routing\Route`, wired via `setRouteResolver()` — never mock `Request` itself:**

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

**Mockery gotcha:** `->with()` matches on exact arity. `$request->route('item')` internally calls `Route::parameter('item', $default)` with **two** positional arguments (`$default` defaults to `null`), not one. A stub declared `->with('item')` will never match and throws `NoMatchingExpectationException` — declare `->with('item', null)` explicitly.

**If the middleware redirects via a named route** (`redirect()->route($name, ...)`), that name must resolve against a real route or `route()` throws `RouteNotFoundException` — the mock alone isn't enough. Add one generic, `testing`-environment-gated route at the bottom of `routes/web.php` (registered at normal boot time, so it's fully indexed):

```php
if (app()->environment('testing')) {
    Route::get('/test-route', fn () => response('ok'))->name('test-route');
}
```

Every test case's mocked `getName()` can return this same `'test-route'` name — the middleware only cares about the *name* it gets back from `$request->route()->getName()`, never the target route's own URI or parameters.

**Model creation:** use `Item::factory()->make(...)`, never `->create()` — the middleware never touches the database, so there's no reason to persist.
