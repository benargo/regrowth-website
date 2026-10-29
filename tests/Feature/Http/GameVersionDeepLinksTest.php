<?php

namespace Tests\Feature\Http;

use App\Contracts\Actions\GameVersion\SharesCurrentGameVersionSession;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\User;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

#[Group('platform')]
class GameVersionDeepLinksTest extends DashboardTestCase
{
    #[Test]
    #[Group('happy-path')]
    public function a_deep_link_on_any_page_remembers_the_version_and_drops_the_parameter(): void
    {
        $gameVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2024-01-01')]);

        $response = $this->get(route('loot.index', ['game_version' => $gameVersion->slug]));

        $response->assertStatus(303)->assertRedirect(route('loot.index'));
        $response->assertSessionHas(SharesCurrentGameVersionSession::SESSION_KEY, $gameVersion->slug);
    }

    #[Test]
    #[DataProvider('versionIndependentRoutes')]
    public function a_version_independent_route_leaves_the_session_alone(string $routeName): void
    {
        $gameVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2024-01-01')]);
        $otherVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2023-01-01')]);
        $parameters = $routeName === 'characters.edit'
            ? ['character' => ($character = Character::factory()->withPlayableClass()->create(['game_version_id' => $otherVersion->id])), 'slug' => $character->name]
            : [];

        $user = User::factory()->withPermissions('view-officer-dashboard', 'edit-datasets', 'view-planned-absences', 'create-planned-absences', 'update-characters')->create();

        $response = $this->actingAs($user)
            ->withSession([SharesCurrentGameVersionSession::SESSION_KEY => $otherVersion->slug])
            ->get(route($routeName, $parameters + ['game_version' => $gameVersion->slug]));

        $response->assertOk();
        $response->assertSessionHas(SharesCurrentGameVersionSession::SESSION_KEY, $otherVersion->slug);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function versionIndependentRoutes(): array
    {
        return [
            'the phases index' => ['management.phases.index'],
            'the GRM import form' => ['management.grm.create'],
            'the raiding absence form' => ['raiding.absences.create'],
            'the character edit form' => ['characters.edit'],
        ];
    }

    #[Test]
    public function the_phase_create_pre_fill_id_passes_through(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $response = $this->actingAs($this->officer)->get(route('management.phases.create', ['game_version' => $gameVersion->id]));

        $response->assertOk();
        $response->assertSessionMissing(SharesCurrentGameVersionSession::SESSION_KEY);
    }
}
