<?php

namespace Tests\Feature\Middleware;

use App\Actions\GameVersion\ListSelectableGameVersions;
use App\Contracts\Actions\GameVersion\SharesCurrentGameVersionSession;
use App\Contracts\Http\Middleware\SharesGameVersionSwitchFlash;
use App\Http\Middleware\RemembersRecordGameVersion;
use App\Models\GameVersion;
use App\Models\Item;
use App\Models\Phase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
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

        $response = (new RemembersRecordGameVersion)->handle($request, fn () => response('next'), 'record', 'index');

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
            $request->session()->put(SharesGameVersionSwitchFlash::SWITCHED_FLASH_KEY, true);
        }

        $response = (new RemembersRecordGameVersion)->handle($request, fn () => response('next'), 'record', 'index');

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
        ];
    }

    #[Test]
    #[DataProvider('sectionIndexes')]
    public function right_after_an_explicit_switch_it_redirects_to_the_section_index(string $routeName, string $indexRoute, string $expectedRoute): void
    {
        $request = $this->requestFor($this->itemOwnedBy($this->otherVersion), routeName: $routeName);
        $request->session()->put(SharesGameVersionSwitchFlash::SWITCHED_FLASH_KEY, true);

        $response = (new RemembersRecordGameVersion)->handle($request, fn () => response('next'), 'record', $indexRoute);

        $this->assertSame(route($expectedRoute), $response->getTargetUrl());
        $this->assertNull($request->session()->get(SharesCurrentGameVersionSession::SESSION_KEY));
        $this->assertFalse($request->session()->has(SharesGameVersionSwitchFlash::SWITCHED_FLASH_KEY));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function sectionIndexes(): array
    {
        return [
            'a raiding raid' => ['raiding.raids.show', 'raids.index', 'raiding.raids.index'],
            'a loot boss' => ['loot.bosses.show', 'raids.index', 'loot.raids.index'],
            'an item' => ['loot.items.show', 'index', 'loot.index'],
            'an event' => ['raiding.plans.show', 'events.index', 'raiding.events.index'],
            'a report' => ['raiding.reports.show', 'reports.index', 'raiding.reports.index'],
        ];
    }

    #[Test]
    public function right_after_switching_to_the_records_own_version_it_stays_on_the_record(): void
    {
        $request = $this->requestFor($this->itemOwnedBy($this->currentVersion));
        $request->session()->put(SharesGameVersionSwitchFlash::SWITCHED_FLASH_KEY, true);

        $response = (new RemembersRecordGameVersion)->handle($request, fn () => response('next'), 'record', 'index');

        $this->assertSame('next', $response->getContent());
    }

    #[Test]
    public function every_record_route_names_an_index_route_that_exists(): void
    {
        $recordRoutes = collect(RouteFacade::getRoutes()->getRoutes())
            ->mapWithKeys(fn (Route $route): array => [
                $route->getName() => collect($route->gatherMiddleware())
                    ->first(fn (mixed $middleware): bool => is_string($middleware) && str_starts_with($middleware, RemembersRecordGameVersion::class.':')),
            ])
            ->filter();

        $this->assertNotEmpty($recordRoutes);

        foreach ($recordRoutes as $routeName => $middleware) {
            $section = Str::before($routeName, '.');
            $indexRoute = Str::afterLast($middleware, ',');

            $this->assertTrue(RouteFacade::has("{$section}.{$indexRoute}"), "{$routeName} → {$section}.{$indexRoute}");
        }
    }

    #[Test]
    public function it_leaves_the_session_alone_for_a_record_of_the_current_version(): void
    {
        $request = $this->requestFor($this->itemOwnedBy($this->currentVersion));

        (new RemembersRecordGameVersion)->handle($request, fn () => response('next'), 'record', 'index');

        $this->assertNull($request->session()->get(SharesCurrentGameVersionSession::SESSION_KEY));
    }

    #[Test]
    public function it_leaves_the_session_alone_for_a_record_without_a_game_version(): void
    {
        $request = $this->requestFor($this->itemOwnedBy(null));

        (new RemembersRecordGameVersion)->handle($request, fn () => response('next'), 'record', 'index');

        $this->assertNull($request->session()->get(SharesCurrentGameVersionSession::SESSION_KEY));
    }

    #[Test]
    public function it_ignores_a_route_parameter_that_does_not_belong_to_a_game_version(): void
    {
        $request = $this->requestFor(Phase::factory()->make());

        $response = (new RemembersRecordGameVersion)->handle($request, fn () => response('next'), 'record', 'index');

        $this->assertNull($request->session()->get(SharesCurrentGameVersionSession::SESSION_KEY));
        $this->assertSame('next', $response->getContent());
    }

    private function requestFor(mixed $record, string $method = 'GET', string $routeName = 'loot.items.show'): Request
    {
        /** @var Route&MockInterface $route */
        $route = Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('record', null)->andReturn($record);
        $route->shouldReceive('getName')->andReturn($routeName);

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
