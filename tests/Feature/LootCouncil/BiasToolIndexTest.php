<?php

namespace Tests\Feature\LootCouncil;

use App\Models\TBC\Phase;
use App\Models\TBC\Raid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BiasToolIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_loot_index_requires_authentication(): void
    {
        $response = $this->get('/loot');

        $response->assertRedirect('/login');
    }

    public function test_loot_index_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get('/loot');

        $response->assertForbidden();
    }

    public function test_loot_index_forbids_users_with_no_roles(): void
    {
        $user = User::factory()->noRoles()->create();

        $response = $this->actingAs($user)->get('/loot');

        $response->assertForbidden();
    }

    public function test_loot_index_redirects_to_current_phase(): void
    {
        $user = User::factory()->member()->create();

        $oldPhase = Phase::factory()->create(['start_date' => now()->subMonths(6)]);
        $currentPhase = Phase::factory()->create(['start_date' => now()->subDay()]);
        $futurePhase = Phase::factory()->create(['start_date' => now()->addMonth()]);

        Raid::factory()->create(['phase_id' => $currentPhase->id]);

        $response = $this->actingAs($user)->get('/loot');

        $response->assertRedirect(route('loot.phase', ['phase' => $currentPhase->id]));
    }

    public function test_loot_index_follows_redirect_to_phase_page(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        Raid::factory()->create(['phase_id' => $phase->id]);

        $response = $this->actingAs($user)->get('/loot');

        $response->assertRedirect();
        $this->actingAs($user)->get($response->headers->get('Location'))->assertOk();
    }
}
