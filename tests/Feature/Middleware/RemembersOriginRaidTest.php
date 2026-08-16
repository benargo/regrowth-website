<?php

namespace Tests\Feature\Middleware;

use App\Contracts\Http\Middleware\SharesOriginRaidSession;
use App\Http\Middleware\RemembersOriginRaid;
use App\Models\Item;
use App\Models\Raid;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
#[Group('loot')]
class RemembersOriginRaidTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_applies_the_remembered_raid_when_the_item_drops_in_it(): void
    {
        $raid = $this->makeRaid(1);
        $item = $this->makeItemInRaids(2, [$raid]);

        $session = $this->sessionReturning($raid->id);
        $request = $this->requestForItem($item, $session);

        (new RemembersOriginRaid)->handle($request, fn () => response('next'));

        $this->assertSame($raid->id, $request->attributes->get('origin_raid_id'));
    }

    #[Test]
    #[Group('edge-case')]
    public function it_resolves_to_null_when_nothing_is_remembered(): void
    {
        $item = $this->makeItemInRaids(2, [$this->makeRaid(1)]);

        $session = $this->sessionReturning(null);
        $request = $this->requestForItem($item, $session);

        (new RemembersOriginRaid)->handle($request, fn () => response('next'));

        $this->assertNull($request->attributes->get('origin_raid_id'));
    }

    #[Test]
    #[Group('edge-case')]
    public function it_ignores_a_remembered_raid_the_item_does_not_drop_in(): void
    {
        $blackTemple = $this->makeRaid(1, ['name' => 'Black Temple']);
        $karazhan = $this->makeRaid(2, ['name' => 'Karazhan']);
        $item = $this->makeItemInRaids(3, [$karazhan]);

        $session = $this->sessionReturning($blackTemple->id);
        $request = $this->requestForItem($item, $session);

        (new RemembersOriginRaid)->handle($request, fn () => response('next'));

        $this->assertNull($request->attributes->get('origin_raid_id'));
    }

    #[Test]
    public function it_always_calls_the_next_middleware(): void
    {
        $item = $this->makeItemInRaids(2, [$this->makeRaid(1)]);

        $session = $this->sessionReturning(null);
        $request = $this->requestForItem($item, $session);

        $response = (new RemembersOriginRaid)->handle($request, fn () => response('next'));

        $this->assertSame('next', $response->getContent());
    }

    // ↓ Helpers at the bottom

    private function sessionReturning(?int $raidId): Session&MockInterface
    {
        $session = Mockery::mock(Session::class);
        $session->shouldReceive('get')->with(SharesOriginRaidSession::SESSION_KEY)->andReturn($raidId);

        return $session;
    }

    private function requestForItem(Item $item, Session&MockInterface $session): Request
    {
        /** @var Route&MockInterface $route */
        $route = Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('item', null)->andReturn($item);

        $request = Request::create('/loot/items/'.$item->id, 'GET');
        $request->setRouteResolver(fn () => $route);

        $this->app->instance(Session::class, $session);
        $request->setLaravelSession($session);

        return $request;
    }

    /**
     * Build an unpersisted raid with an explicit id.
     *
     * `id` isn't fillable, so the factory's `make()` silently drops it unless
     * forced — needed here because the session assertions depend on a real,
     * non-null id.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function makeRaid(int $id, array $attributes = []): Raid
    {
        return Raid::factory()->make($attributes)->forceFill(['id' => $id]);
    }

    /**
     * Build an unpersisted item with an explicit id and its `raids` relation
     * hydrated in memory.
     *
     * `inRaids()`/`trashDrop()` attach the pivot via `afterCreating()`, which
     * never fires on `make()`, so the relation is set directly instead of
     * relying on the factory state.
     *
     * @param  array<int, Raid>  $raids
     * @param  array<string, mixed>  $attributes
     */
    private function makeItemInRaids(int $id, array $raids, array $attributes = []): Item
    {
        $item = Item::factory()->make($attributes)->forceFill(['id' => $id]);
        $item->setRelation('raids', collect($raids));

        return $item;
    }
}
