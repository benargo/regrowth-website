<?php

namespace Tests\Feature\Middleware;

use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Http\Middleware\RemembersCurrentGameVersion;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class RemembersCurrentGameVersionTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_remembers_the_requested_game_version_and_calls_the_next_middleware(): void
    {
        $request = $this->requestWithSession(['game_version' => 'mop']);

        $response = (new RemembersCurrentGameVersion)->handle($request, fn () => response('next'));

        $this->assertSame('mop', $request->session()->get(ResolveCurrentGameVersion::SESSION_KEY));
        $this->assertSame('next', $response->getContent());
    }

    /**
     * @param  array<string, string>  $query
     * @param  array<string, string>  $headers
     */
    #[Test]
    #[DataProvider('requestsThatLeaveTheSessionAlone')]
    public function it_leaves_the_session_alone(array $query, array $headers): void
    {
        $request = $this->requestWithSession($query);
        $request->headers->add($headers);
        $request->session()->put(ResolveCurrentGameVersion::SESSION_KEY, 'tbc');

        $response = (new RemembersCurrentGameVersion)->handle($request, fn () => response('next'));

        $this->assertSame('tbc', $request->session()->get(ResolveCurrentGameVersion::SESSION_KEY));
        $this->assertSame('next', $response->getContent());
    }

    /**
     * @return array<string, array{array<string, string>, array<string, string>}>
     */
    public static function requestsThatLeaveTheSessionAlone(): array
    {
        return [
            'no game version' => [[], []],
            'an empty game version' => [['game_version' => ''], []],
            'a prefetch' => [['game_version' => 'mop'], ['Purpose' => 'prefetch']],
        ];
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
