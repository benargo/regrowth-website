<?php

namespace Tests\Feature\LootCouncil;

use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BiasToolPhaseTest extends TestCase
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

    protected function phaseUrl(Phase $phase): string
    {
        return "/loot/phases/phase-{$phase->id}";
    }

    #[Test]
    public function loot_phase_requires_authentication(): void
    {
        $phase = Phase::factory()->started()->create();

        $response = $this->get($this->phaseUrl($phase));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function loot_phase_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $phase = Phase::factory()->started()->create();

        $response = $this->actingAs($user)->get($this->phaseUrl($phase));

        $response->assertForbidden();
    }

    #[Test]
    public function loot_phase_forbids_users_with_no_roles(): void
    {
        $user = User::factory()->noRoles()->create();
        $phase = Phase::factory()->started()->create();

        $response = $this->actingAs($user)->get($this->phaseUrl($phase));

        $response->assertForbidden();
    }

    #[Test]
    public function loot_phase_redirects_to_first_raid(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);

        $response = $this->actingAs($user)->get($this->phaseUrl($phase));

        $response->assertRedirect(route('loot.raids.show', ['raid' => $raid->id, 'name' => Str::slug($raid->name)]));
    }

    #[Test]
    public function loot_phase_redirects_to_last_visited_raid(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $firstRaid = Raid::factory()->create(['phase_id' => $phase->id]);
        $lastVisitedRaid = Raid::factory()->create(['phase_id' => $phase->id]);

        $response = $this->actingAs($user)
            ->withSession(["loot.last_visited_raid.{$phase->id}" => $lastVisitedRaid->id])
            ->get($this->phaseUrl($phase));

        $response->assertRedirect(route('loot.raids.show', ['raid' => $lastVisitedRaid->id, 'name' => Str::slug($lastVisitedRaid->name)]));
    }

    #[Test]
    public function loot_phase_falls_back_to_first_raid_when_last_visited_raid_not_in_phase(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $otherPhaseRaid = Raid::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(["loot.last_visited_raid.{$phase->id}" => $otherPhaseRaid->id])
            ->get($this->phaseUrl($phase));

        $response->assertRedirect(route('loot.raids.show', ['raid' => $raid->id, 'name' => Str::slug($raid->name)]));
    }
}
