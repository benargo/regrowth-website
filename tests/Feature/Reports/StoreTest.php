<?php

namespace Tests\Feature\Reports;

use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\GuildTag;
use App\Models\Permission;
use App\Models\Raids\Report;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function grantManageReports(): void
    {
        $officerRole = DiscordRole::firstOrCreate(['id' => '829021769448816691'], ['name' => 'Officer', 'position' => 5, 'is_visible' => true]);
        $officerRole->givePermissionTo(Permission::firstOrCreate(['name' => 'manage-reports', 'guard_name' => 'web']));
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * @return array<string, mixed>
     */
    private function validStoreData(GuildTag $tag): array
    {
        return [
            'title' => 'Sunday Karazhan',
            'start_time' => '2025-01-05 20:00',
            'end_time' => '2025-01-05 23:30',
            'guild_tag_id' => $tag->id,
            'zone_id' => 1000,
        ];
    }

    // ==================== Access Control ====================

    #[Test]
    public function store_requires_authentication(): void
    {
        $response = $this->post(route('raiding.reports.store'), []);

        $response->assertRedirect('/login');
    }

    #[Test]
    public function store_forbids_users_without_manage_reports(): void
    {
        $tag = GuildTag::factory()->withoutPhase()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('raiding.reports.store'), $this->validStoreData($tag));

        $response->assertForbidden();
    }

    // ==================== Happy Path ====================

    #[Test]
    public function store_creates_report_with_valid_data(): void
    {
        $this->grantManageReports();
        Zone::factory()->create(['id' => 1000, 'name' => 'Karazhan']);
        $tag = GuildTag::factory()->withoutPhase()->create();
        $user = User::factory()->officer()->create();

        $this->actingAs($user)->post(route('raiding.reports.store'), $this->validStoreData($tag));

        $this->assertDatabaseHas('raid_reports', [
            'title' => 'Sunday Karazhan',
            'zone_id' => 1000,
            'guild_tag_id' => $tag->id,
            'code' => null,
        ]);
    }

    #[Test]
    public function store_redirects_to_show_with_success_flash(): void
    {
        $this->grantManageReports();
        Zone::factory()->create(['id' => 1000]);
        $tag = GuildTag::factory()->withoutPhase()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('raiding.reports.store'), $this->validStoreData($tag));

        $report = Report::where('title', 'Sunday Karazhan')->firstOrFail();
        $response->assertRedirect(route('raiding.reports.show', $report));
        $response->assertSessionHas('success', 'New report created');
    }

    #[Test]
    public function store_attaches_characters_to_report(): void
    {
        $this->grantManageReports();
        Zone::factory()->create(['id' => 1000]);
        $tag = GuildTag::factory()->withoutPhase()->create();
        $character = Character::factory()->create();
        $user = User::factory()->officer()->create();

        $this->actingAs($user)->post(route('raiding.reports.store'), array_merge(
            $this->validStoreData($tag),
            ['character_ids' => [$character->id]]
        ));

        $report = Report::where('title', 'Sunday Karazhan')->firstOrFail();
        $this->assertDatabaseHas('pivot_characters_raid_reports', [
            'raid_report_id' => $report->id,
            'character_id' => $character->id,
            'presence' => 1,
        ]);
    }

    #[Test]
    public function store_creates_bidirectional_links(): void
    {
        $this->grantManageReports();
        Zone::factory()->create(['id' => 1000]);
        $tag = GuildTag::factory()->withoutPhase()->create();
        $existingReport = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $this->actingAs($user)->post(route('raiding.reports.store'), array_merge(
            $this->validStoreData($tag),
            ['linked_report_ids' => [$existingReport->id]]
        ));

        $newReport = Report::where('title', 'Sunday Karazhan')->firstOrFail();

        $this->assertDatabaseHas('raid_report_links', [
            'report_1' => $newReport->id,
            'report_2' => $existingReport->id,
        ]);
        $this->assertDatabaseHas('raid_report_links', [
            'report_1' => $existingReport->id,
            'report_2' => $newReport->id,
        ]);
    }

    #[Test]
    public function store_creates_links_to_reports_already_linked_to_selected(): void
    {
        $this->grantManageReports();
        Zone::factory()->create(['id' => 1000]);
        $tag = GuildTag::factory()->withoutPhase()->create();
        $reportA = Report::factory()->withoutGuildTag()->create();
        $reportB = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        // A is already linked to B
        $reportA->linkedReports()->attach($reportB->id, ['created_by' => $user->id]);
        $reportB->linkedReports()->attach($reportA->id, ['created_by' => $user->id]);

        // Store new report linked to A only — it should also be linked to B transitively
        $this->actingAs($user)->post(route('raiding.reports.store'), array_merge(
            $this->validStoreData($tag),
            ['linked_report_ids' => [$reportA->id]]
        ));

        $newReport = Report::where('title', 'Sunday Karazhan')->firstOrFail();

        $this->assertDatabaseHas('raid_report_links', ['report_1' => $newReport->id, 'report_2' => $reportB->id]);
        $this->assertDatabaseHas('raid_report_links', ['report_1' => $reportB->id, 'report_2' => $newReport->id]);
    }

    #[Test]
    public function store_sets_loot_councillor_flag_on_attending_character(): void
    {
        $this->grantManageReports();
        Zone::factory()->create(['id' => 1000]);
        $tag = GuildTag::factory()->withoutPhase()->create();
        $character = Character::factory()->lootCouncillor()->create();
        $user = User::factory()->officer()->create();

        $this->actingAs($user)->post(route('raiding.reports.store'), array_merge(
            $this->validStoreData($tag),
            ['character_ids' => [$character->id], 'loot_councillor_ids' => [$character->id]]
        ));

        $report = Report::where('title', 'Sunday Karazhan')->firstOrFail();
        $this->assertDatabaseHas('pivot_characters_raid_reports', [
            'raid_report_id' => $report->id,
            'character_id' => $character->id,
            'presence' => 1,
            'is_loot_councillor' => true,
        ]);
    }

    #[Test]
    public function store_attaches_absent_loot_councillor_with_presence_zero(): void
    {
        $this->grantManageReports();
        Zone::factory()->create(['id' => 1000]);
        $tag = GuildTag::factory()->withoutPhase()->create();
        $character = Character::factory()->lootCouncillor()->create();
        $user = User::factory()->officer()->create();

        $this->actingAs($user)->post(route('raiding.reports.store'), array_merge(
            $this->validStoreData($tag),
            ['loot_councillor_ids' => [$character->id]]
        ));

        $report = Report::where('title', 'Sunday Karazhan')->firstOrFail();
        $this->assertDatabaseHas('pivot_characters_raid_reports', [
            'raid_report_id' => $report->id,
            'character_id' => $character->id,
            'presence' => 0,
            'is_loot_councillor' => true,
        ]);
    }

    // ==================== Validation ====================

    #[Test]
    public function store_rejects_non_loot_councillor_character_for_loot_councillor_ids(): void
    {
        $this->grantManageReports();
        $tag = GuildTag::factory()->withoutPhase()->create();
        $character = Character::factory()->create(['is_loot_councillor' => false]);
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('raiding.reports.store'), array_merge(
            $this->validStoreData($tag),
            ['loot_councillor_ids' => [$character->id]]
        ));

        $response->assertSessionHasErrors(['loot_councillor_ids.0']);
    }

    #[Test]
    public function store_rejects_missing_title(): void
    {
        $this->grantManageReports();
        $tag = GuildTag::factory()->withoutPhase()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('raiding.reports.store'), array_merge(
            $this->validStoreData($tag),
            ['title' => '']
        ));

        $response->assertSessionHasErrors(['title']);
    }

    #[Test]
    public function store_rejects_end_time_before_start_time(): void
    {
        $this->grantManageReports();
        $tag = GuildTag::factory()->withoutPhase()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('raiding.reports.store'), array_merge(
            $this->validStoreData($tag),
            ['start_time' => '2025-01-05 23:30', 'end_time' => '2025-01-05 20:00']
        ));

        $response->assertSessionHasErrors(['end_time']);
    }

    #[Test]
    public function store_rejects_invalid_guild_tag_id(): void
    {
        $this->grantManageReports();
        $tag = GuildTag::factory()->withoutPhase()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('raiding.reports.store'), array_merge(
            $this->validStoreData($tag),
            ['guild_tag_id' => 99999]
        ));

        $response->assertSessionHasErrors(['guild_tag_id']);
    }

    #[Test]
    public function store_rejects_nonexistent_character_id(): void
    {
        $this->grantManageReports();
        $tag = GuildTag::factory()->withoutPhase()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('raiding.reports.store'), array_merge(
            $this->validStoreData($tag),
            ['character_ids' => [99999]]
        ));

        $response->assertSessionHasErrors(['character_ids.0']);
    }

    #[Test]
    public function store_rejects_nonexistent_linked_report_id(): void
    {
        $this->grantManageReports();
        $tag = GuildTag::factory()->withoutPhase()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('raiding.reports.store'), array_merge(
            $this->validStoreData($tag),
            ['linked_report_ids' => ['00000000-0000-0000-0000-000000000000']]
        ));

        $response->assertSessionHasErrors(['linked_report_ids.0']);
    }

    #[Test]
    public function store_rejects_invalid_zone_id(): void
    {
        $this->grantManageReports();
        $tag = GuildTag::factory()->withoutPhase()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('raiding.reports.store'), array_merge(
            $this->validStoreData($tag),
            ['zone_id' => 9999]
        ));

        $response->assertSessionHasErrors(['zone_id']);
    }
}
