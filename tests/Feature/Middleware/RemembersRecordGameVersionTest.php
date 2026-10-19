<?php

namespace Tests\Feature\Middleware;

use App\Actions\GameVersion\ListSelectableGameVersions;
use App\Contracts\Actions\GameVersion\SharesCurrentGameVersionSession;
use App\Http\Middleware\RemembersRecordGameVersion;
use App\Models\GameVersion;
use App\Models\Item;
use App\Models\Phase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class RemembersRecordGameVersionTest extends TestCase
{
    private GameVersion $currentVersion;

    private GameVersion $otherVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currentVersion = GameVersion::factory()->make(['id' => 1, 'slug' => 'tbc', 'release_date' => now()->subYear()]);
        $this->otherVersion = GameVersion::factory()->make(['id' => 2, 'slug' => 'mop', 'release_date' => now()->subYears(2)]);

        ListSelectableGameVersions::shouldRun()->andReturn(new Collection([$this->currentVersion, $this->otherVersion]));
    }

    #[Test]
    #[Group('happy-path')]
    public function it_remembers_the_version_that_owns_the_record(): void
    {
        $request = $this->requestFor($this->itemOwnedBy($this->otherVersion));

        $response = (new RemembersRecordGameVersion)->handle($request, fn () => response('next'), 'record');

        $this->assertSame('mop', $request->session()->get(SharesCurrentGameVersionSession::SESSION_KEY));
        $this->assertSame('next', $response->getContent());
    }

    /**
     * @param  array<string, string>  $headers
     */
    #[Test]
    #[DataProvider('requestsThatLeaveTheSessionAlone')]
    public function it_leaves_the_session_alone(string $method, array $headers, bool $wasJustSwitched): void
    {
        $request = $this->requestFor($this->itemOwnedBy($this->otherVersion), $method);
        $request->headers->add($headers);

        if ($wasJustSwitched) {
            $request->session()->put(RemembersRecordGameVersion::SWITCHED_FLASH_KEY, true);
        }

        $response = (new RemembersRecordGameVersion)->handle($request, fn () => response('next'), 'record');

        $this->assertNull($request->session()->get(SharesCurrentGameVersionSession::SESSION_KEY));
        $this->assertSame('next', $response->getContent());
    }

    /**
     * @return array<string, array{string, array<string, string>, bool}>
     */
    public static function requestsThatLeaveTheSessionAlone(): array
    {
        return [
            'a prefetch' => ['GET', ['Purpose' => 'prefetch'], false],
            'a partial reload' => ['GET', ['X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Raids/Show'], false],
            'a POST' => ['POST', [], false],
            'right after an explicit switch' => ['GET', [], true],
        ];
    }

    #[Test]
    public function it_forgets_the_explicit_switch_flag_once_it_has_skipped_a_record_page(): void
    {
        $request = $this->requestFor($this->itemOwnedBy($this->otherVersion));
        $request->session()->put(RemembersRecordGameVersion::SWITCHED_FLASH_KEY, true);

        (new RemembersRecordGameVersion)->handle($request, fn () => response('next'), 'record');

        $this->assertFalse($request->session()->has(RemembersRecordGameVersion::SWITCHED_FLASH_KEY));
        $this->assertNull($request->session()->get(SharesCurrentGameVersionSession::SESSION_KEY));
    }

    #[Test]
    public function it_leaves_the_session_alone_for_a_record_of_the_current_version(): void
    {
        $request = $this->requestFor($this->itemOwnedBy($this->currentVersion));

        (new RemembersRecordGameVersion)->handle($request, fn () => response('next'), 'record');

        $this->assertNull($request->session()->get(SharesCurrentGameVersionSession::SESSION_KEY));
    }

    #[Test]
    public function it_leaves_the_session_alone_for_a_record_without_a_game_version(): void
    {
        $request = $this->requestFor($this->itemOwnedBy(null));

        (new RemembersRecordGameVersion)->handle($request, fn () => response('next'), 'record');

        $this->assertNull($request->session()->get(SharesCurrentGameVersionSession::SESSION_KEY));
    }

    #[Test]
    public function it_ignores_a_route_parameter_that_does_not_belong_to_a_game_version(): void
    {
        $request = $this->requestFor(Phase::factory()->make());

        $response = (new RemembersRecordGameVersion)->handle($request, fn () => response('next'), 'record');

        $this->assertNull($request->session()->get(SharesCurrentGameVersionSession::SESSION_KEY));
        $this->assertSame('next', $response->getContent());
    }

    private function requestFor(mixed $record, string $method = 'GET'): Request
    {
        /** @var Route&MockInterface $route */
        $route = Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('record', null)->andReturn($record);

        $request = Request::create('/records/1', $method);
        $request->setRouteResolver(fn () => $route);
        $request->setLaravelSession(app('session.store'));

        return $request;
    }

    private function itemOwnedBy(?GameVersion $gameVersion): Item
    {
        return Item::factory()->make(['game_version_id' => $gameVersion?->id]);
    }
}
