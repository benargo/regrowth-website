<?php

namespace Tests\Feature\LootBiasTool;

use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('loot')]
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function loot_index_allows_unauthenticated_users(): void
    {
        $response = $this->get('/loot');

        $response->assertOk();
    }

    #[Group('authorization')]
    #[Test]
    public function loot_index_allows_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get('/loot');

        $response->assertOk();
    }

    #[Group('authorization')]
    #[Test]
    public function loot_index_allows_users_with_no_roles(): void
    {
        $user = User::factory()->noRoles()->create();

        $response = $this->actingAs($user)->get('/loot');

        $response->assertOk();
    }

    #[Test]
    public function loot_index_renders_inertia_page(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        Raid::factory()->create(['phase_id' => $phase->id]);

        $response = $this->actingAs($user)->get('/loot');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Loot/Index'));
    }

    #[Test]
    public function loot_index_passes_raids_with_phase_number_as_props(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        Raid::factory()->count(2)->create(['phase_id' => $phase->id]);

        $response = $this->actingAs($user)->get('/loot');

        $response->assertInertia(fn ($page) => $page
            ->component('Loot/Index')
            ->has('raids.data', 2, fn ($r) => $r
                ->has('name')
                ->has('slug')
                ->has('color')
                ->has('background')
                ->has('phase_number')
                ->etc()
            )
        );
    }
}
