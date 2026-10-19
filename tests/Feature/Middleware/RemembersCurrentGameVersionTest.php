<?php

namespace Tests\Feature\Middleware;

use App\Actions\GameVersion\ListSelectableGameVersions;
use App\Contracts\Actions\GameVersion\SharesCurrentGameVersionSession;
use App\Http\Middleware\RemembersCurrentGameVersion;
use App\Models\GameVersion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class RemembersCurrentGameVersionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ListSelectableGameVersions::shouldRun()->andReturn(new Collection([
            GameVersion::factory()->make(['slug' => 'mop']),
            GameVersion::factory()->make(['slug' => 'tbc']),
        ]));
    }

    #[Test]
    #[Group('happy-path')]
    public function it_remembers_the_requested_game_version_and_redirects_to_the_url_without_it(): void
    {
        $request = $this->requestWithSession(['game_version' => 'mop', 'page' => '2']);

        $response = (new RemembersCurrentGameVersion)->handle($request, fn () => response('next'));

        $this->assertSame('mop', $request->session()->get(SharesCurrentGameVersionSession::SESSION_KEY));
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
    public function it_leaves_the_session_alone(string $method, array $query, array $headers): void
    {
        $request = $this->requestWithSession($query, $method);
        $request->headers->add($headers);
        $request->session()->put(SharesCurrentGameVersionSession::SESSION_KEY, 'tbc');

        $response = (new RemembersCurrentGameVersion)->handle($request, fn () => response('next'));

        $this->assertSame('tbc', $request->session()->get(SharesCurrentGameVersionSession::SESSION_KEY));
        $this->assertSame('next', $response->getContent());
    }

    #[Test]
    public function it_ignores_a_game_version_in_the_request_body(): void
    {
        $request = Request::create('/roster', 'GET', server: ['CONTENT_TYPE' => 'application/json'], content: '{"game_version":"mop"}');
        $request->setLaravelSession(app('session.store'));
        $request->session()->put(SharesCurrentGameVersionSession::SESSION_KEY, 'tbc');

        $response = (new RemembersCurrentGameVersion)->handle($request, fn () => response('next'));

        $this->assertSame('tbc', $request->session()->get(SharesCurrentGameVersionSession::SESSION_KEY));
        $this->assertSame('next', $response->getContent());
    }

    /**
     * @return array<string, array{string, array<string, string>, array<string, string>}>
     */
    public static function requestsThatLeaveTheSessionAlone(): array
    {
        return [
            'no game version' => ['GET', [], []],
            'an empty game version' => ['GET', ['game_version' => ''], []],
            'a prefetch' => ['GET', ['game_version' => 'mop'], ['Purpose' => 'prefetch']],
            'an unknown slug' => ['GET', ['game_version' => 'nope'], []],
            'an integer id' => ['GET', ['game_version' => '7'], []],
            'a POST' => ['POST', ['game_version' => 'mop'], []],
            'a PATCH' => ['PATCH', ['game_version' => 'mop'], []],
        ];
    }

    // ↓ Helpers at the bottom

    /**
     * @param  array<string, string>  $query
     */
    private function requestWithSession(array $query, string $method = 'GET'): Request
    {
        $request = Request::create('/roster', $method, $query);
        $request->setLaravelSession(app('session.store'));

        return $request;
    }
}
