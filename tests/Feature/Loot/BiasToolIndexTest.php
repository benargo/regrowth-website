<?php

namespace Tests\Feature\Loot;

use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BiasToolIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $viewLootBiasTool = Permission::firstOrCreate(['name' => 'view-loot-bias-tool', 'guard_name' => 'web']);
        DiscordRole::firstOrCreate(['id' => '829022020301094922'], ['name' => 'Member', 'position' => 2, 'is_visible' => true])->givePermissionTo($viewLootBiasTool);
        DiscordRole::firstOrCreate(['id' => '1265247017215594496'], ['name' => 'Raider', 'position' => 4, 'is_visible' => true])->givePermissionTo($viewLootBiasTool);
        DiscordRole::firstOrCreate(['id' => '829021769448816691'], ['name' => 'Officer', 'position' => 6, 'is_visible' => true])->givePermissionTo($viewLootBiasTool);
    }

    #[Test]
    public function loot_index_requires_authentication(): void
    {
        $response = $this->get('/loot');

        $response->assertRedirect('/login');
    }

    #[Test]
    public function loot_index_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get('/loot');

        $response->assertForbidden();
    }

    #[Test]
    public function loot_index_forbids_users_with_no_roles(): void
    {
        $user = User::factory()->noRoles()->create();

        $response = $this->actingAs($user)->get('/loot');

        $response->assertForbidden();
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
            ->has('raids', 2, fn ($r) => $r
                ->has('name')
                ->has('slug')
                ->has('background')
                ->has('phase', fn ($p) => $p
                    ->has('number')
                    ->etc()
                )
                ->etc()
            )
        );
    }
}
