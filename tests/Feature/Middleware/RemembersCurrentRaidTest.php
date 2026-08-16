<?php

namespace Tests\Feature\Middleware;

use App\Contracts\Http\Middleware\SharesOriginRaidSession;
use App\Http\Middleware\RemembersCurrentRaid;
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
class RemembersCurrentRaidTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_remembers_the_current_raid_in_the_session(): void
    {
        $raid = $this->makeRaid(1);

        $session = Mockery::mock(Session::class);
        $session->shouldReceive('put')->once()->with(SharesOriginRaidSession::SESSION_KEY, $raid->id);

        $request = $this->requestForRaid($raid, $session);

        (new RemembersCurrentRaid)->handle($request, fn () => response('next'));
    }

    #[Test]
    public function it_always_calls_the_next_middleware(): void
    {
        $raid = $this->makeRaid(1);

        $session = Mockery::mock(Session::class);
        $session->shouldReceive('put')->once();

        $request = $this->requestForRaid($raid, $session);

        $response = (new RemembersCurrentRaid)->handle($request, fn () => response('next'));

        $this->assertSame('next', $response->getContent());
    }

    // ↓ Helpers at the bottom

    private function requestForRaid(Raid $raid, Session&MockInterface $session): Request
    {
        /** @var Route&MockInterface $route */
        $route = Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('raid', null)->andReturn($raid);

        $request = Request::create('/loot/raids/'.$raid->id, 'GET');
        $request->setRouteResolver(fn () => $route);

        $this->app->instance(Session::class, $session);
        $request->setLaravelSession($session);

        return $request;
    }

    /**
     * Build an unpersisted raid with an explicit id.
     *
     * `id` isn't fillable, so the factory's `make()` silently drops it unless
     * forced — needed here because the session assertion depends on a real,
     * non-null id.
     */
    private function makeRaid(int $id): Raid
    {
        return Raid::factory()->make()->forceFill(['id' => $id]);
    }
}
