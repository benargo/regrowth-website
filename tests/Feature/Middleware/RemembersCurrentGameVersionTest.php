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
    public function it_remembers_the_requested_game_version_and_redirects_to_the_url_without_it(): void
    {
        $request = $this->requestWithSession(['game_version' => 'mop', 'page' => '2']);

        $response = (new RemembersCurrentGameVersion)->handle($request, fn () => response('next'));

        $this->assertSame('mop', $request->session()->get(ResolveCurrentGameVersion::SESSION_KEY));
        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('http://localhost/roster?page=2', $response->headers->get('Location'));
    }

    #[Test]
    public function it_keeps_flashed_data_for_the_page_it_redirects_to(): void
    {
        $request = $this->requestWithSession(['game_version' => 'mop']);
        $request->session()->flash('success', 'Uploaded.');
        $request->session()->ageFlashData();

        (new RemembersCurrentGameVersion)->handle($request, fn () => response('next'));
        $request->session()->ageFlashData();

        $this->assertSame('Uploaded.', $request->session()->get('success'));
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
