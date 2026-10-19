<?php

namespace Tests\Feature\Http\Controllers\Session;

use App\Contracts\Actions\GameVersion\SharesCurrentGameVersionSession;
use App\Http\Middleware\RemembersRecordGameVersion;
use App\Models\GameVersion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[Group('platform')]
class GameVersionControllerTest extends TestCase
{
    use RefreshDatabase;

    // ==================== switching ====================

    #[Test]
    #[Group('happy-path')]
    public function a_guest_can_switch_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2024-01-01')]);

        $response = $this->from(route('loot.index'))
            ->patch(route('session.game-version.update'), ['game_version' => $gameVersion->slug]);

        $response->assertRedirect(route('loot.index'))->assertStatus(303);
        $response->assertSessionHas(SharesCurrentGameVersionSession::SESSION_KEY, $gameVersion->slug);
    }

    #[Test]
    #[Group('happy-path')]
    public function a_member_can_switch_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2024-01-01')]);
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)
            ->from(route('loot.index'))
            ->patch(route('session.game-version.update'), ['game_version' => $gameVersion->slug]);

        $response->assertRedirect(route('loot.index'))->assertStatus(303);
        $response->assertSessionHas(SharesCurrentGameVersionSession::SESSION_KEY, $gameVersion->slug);
    }

    #[Test]
    public function a_switch_flashes_that_it_was_explicit(): void
    {
        $gameVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2024-01-01')]);

        $response = $this->from(route('loot.index'))
            ->patch(route('session.game-version.update'), ['game_version' => $gameVersion->slug]);

        $response->assertSessionHas(RemembersRecordGameVersion::SWITCHED_FLASH_KEY, true);
    }

    #[Test]
    public function a_switch_that_resets_the_query_flashes_that_it_was_explicit(): void
    {
        $gameVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2024-01-01')]);

        $response = $this->from(route('raiding.reports.index', ['page' => 3]))
            ->patch(route('session.game-version.update'), ['game_version' => $gameVersion->slug, 'reset_query' => true]);

        $response->assertRedirect(route('raiding.reports.index'))->assertStatus(303);
        $response->assertSessionHas(RemembersRecordGameVersion::SWITCHED_FLASH_KEY, true);
    }

    // ==================== query string ====================

    #[Test]
    #[Group('happy-path')]
    public function it_keeps_the_previous_query_string_by_default(): void
    {
        $gameVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2024-01-01')]);
        $previous = route('raiding.attendance.matrix', ['since_date' => '2023-05-01', 'rank_ids' => '3,4']);

        $this->from($previous)
            ->patch(route('session.game-version.update'), ['game_version' => $gameVersion->slug])
            ->assertRedirect($previous);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_drops_the_previous_query_string_when_asked_to_reset_it(): void
    {
        $gameVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2024-01-01')]);

        $response = $this->from(route('raiding.attendance.matrix', ['since_date' => '2023-05-01', 'rank_ids' => '3,4']))
            ->patch(route('session.game-version.update'), ['game_version' => $gameVersion->slug, 'reset_query' => true]);

        $response->assertRedirect(route('raiding.attendance.matrix'))->assertStatus(303);
        $response->assertSessionHas(SharesCurrentGameVersionSession::SESSION_KEY, $gameVersion->slug);
    }

    // ==================== validation ====================

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_reset_query_flag_that_is_not_a_boolean(): void
    {
        $gameVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2024-01-01')]);

        $this->patch(route('session.game-version.update'), ['game_version' => $gameVersion->slug, 'reset_query' => 'soon'])
            ->assertSessionHasErrors('reset_query');
    }

    #[Test]
    #[Group('validation')]
    #[TestWith([['game_version' => 'nope']])]
    #[TestWith([[]])]
    #[TestWith([['game_version' => 7]])]
    public function it_rejects_an_unknown_or_missing_slug_and_leaves_the_session_alone(array $payload): void
    {
        $gameVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2024-01-01')]);

        $response = $this->withSession([SharesCurrentGameVersionSession::SESSION_KEY => $gameVersion->slug])
            ->patch(route('session.game-version.update'), $payload);

        $response->assertSessionHasErrors('game_version');
        $response->assertSessionHas(SharesCurrentGameVersionSession::SESSION_KEY, $gameVersion->slug);
    }

    #[Test]
    #[Group('validation')]
    public function it_tells_the_user_an_unknown_slug_is_invalid(): void
    {
        $this->patch(route('session.game-version.update'), ['game_version' => 'nope'])
            ->assertSessionHasErrors(['game_version' => 'The selected game version is invalid.']);
    }

    #[Test]
    #[Group('validation')]
    public function it_tells_the_user_a_game_version_is_required(): void
    {
        $this->patch(route('session.game-version.update'), [])
            ->assertSessionHasErrors(['game_version' => 'The game version field is required.']);
    }

    #[Test]
    public function the_route_is_throttled(): void
    {
        $middleware = Route::getRoutes()->getByName('session.game-version.update')->gatherMiddleware();

        $this->assertContains('throttle:60,1', $middleware);
    }
}
