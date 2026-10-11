<?php

namespace Tests\Feature\Middleware;

use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Http\Middleware\RemembersCurrentGameVersion;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class RemembersCurrentGameVersionTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_remembers_the_requested_game_version_in_the_session(): void
    {
        $request = $this->requestWithSession(['game_version' => 'mop']);

        (new RemembersCurrentGameVersion)->handle($request, fn () => response('next'));

        $this->assertSame('mop', $request->session()->get(ResolveCurrentGameVersion::SESSION_KEY));
    }

    #[Test]
    public function it_leaves_the_session_alone_without_a_game_version(): void
    {
        $request = $this->requestWithSession([]);
        $request->session()->put(ResolveCurrentGameVersion::SESSION_KEY, 'tbc');

        (new RemembersCurrentGameVersion)->handle($request, fn () => response('next'));

        $this->assertSame('tbc', $request->session()->get(ResolveCurrentGameVersion::SESSION_KEY));
    }

    #[Test]
    public function it_leaves_the_session_alone_for_an_empty_game_version(): void
    {
        $request = $this->requestWithSession(['game_version' => '']);
        $request->session()->put(ResolveCurrentGameVersion::SESSION_KEY, 'tbc');

        (new RemembersCurrentGameVersion)->handle($request, fn () => response('next'));

        $this->assertSame('tbc', $request->session()->get(ResolveCurrentGameVersion::SESSION_KEY));
    }

    #[Test]
    public function it_leaves_the_session_alone_when_prefetching(): void
    {
        $request = $this->requestWithSession(['game_version' => 'mop']);
        $request->headers->set('Purpose', 'prefetch');
        $request->session()->put(ResolveCurrentGameVersion::SESSION_KEY, 'tbc');

        (new RemembersCurrentGameVersion)->handle($request, fn () => response('next'));

        $this->assertSame('tbc', $request->session()->get(ResolveCurrentGameVersion::SESSION_KEY));
    }

    #[Test]
    public function it_always_calls_the_next_middleware(): void
    {
        $request = $this->requestWithSession(['game_version' => 'mop']);

        $response = (new RemembersCurrentGameVersion)->handle($request, fn () => response('next'));

        $this->assertSame('next', $response->getContent());
    }

    // ↓ Helpers at the bottom

    /**
     * @param  array<string, string>  $query
     */
    private function requestWithSession(array $query): Request
    {
        $request = Request::create('/roster', 'GET', $query);
        $request->setLaravelSession(new Store('test', new ArraySessionHandler(120)));

        return $request;
    }
}
