<?php

namespace Tests\Feature\Reports;

use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\Raids\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UpdateTest extends TestCase
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

    // ==================== Access Control ====================

    #[Test]
    public function update_requires_authentication(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();

        $response = $this->patch(route('raiding.reports.update', $report), [
            'links' => ['action' => 'create', 'link_ids' => []],
        ]);

        $response->assertRedirect('/login');
    }

    #[Test]
    public function update_returns_forbidden_without_manage_reports(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $other = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->patch(route('raiding.reports.update', $report), [
            'links' => ['action' => 'create', 'link_ids' => [$other->id]],
        ]);

        $response->assertForbidden();
    }

    // ==================== Validation ====================

    #[Test]
    public function update_with_empty_payload_is_a_no_op(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->patch(route('raiding.reports.update', $report), []);

        $response->assertRedirect();
    }

    #[Test]
    public function update_rejects_invalid_action(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->patch(route('raiding.reports.update', $report), [
            'links' => ['action' => 'invalid', 'link_ids' => []],
        ]);

        $response->assertSessionHasErrors(['links.action']);
    }

    #[Test]
    public function update_rejects_empty_link_ids_for_create_action(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->patch(route('raiding.reports.update', $report), [
            'links' => ['action' => 'create', 'link_ids' => []],
        ]);

        $response->assertSessionHasErrors(['links.link_ids']);
    }

    #[Test]
    public function update_rejects_missing_link_ids_for_create_action(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->patch(route('raiding.reports.update', $report), [
            'links' => ['action' => 'create'],
        ]);

        $response->assertSessionHasErrors(['links.link_ids']);
    }

    #[Test]
    public function update_rejects_nonexistent_report_id(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->patch(route('raiding.reports.update', $report), [
            'links' => ['action' => 'create', 'link_ids' => ['00000000-0000-0000-0000-000000000000']],
        ]);

        $response->assertSessionHasErrors(['links.link_ids.0']);
    }

    #[Test]
    public function update_rejects_current_report_in_link_ids(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->patch(route('raiding.reports.update', $report), [
            'links' => ['action' => 'create', 'link_ids' => [$report->id]],
        ]);

        $response->assertSessionHasErrors(['links.link_ids.0']);
    }

    // ==================== links: create ====================

    #[Test]
    public function update_creates_bidirectional_link_between_current_and_selected(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $other = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $this->actingAs($user)->patch(route('raiding.reports.update', $report), [
            'links' => ['action' => 'create', 'link_ids' => [$other->id]],
        ]);

        // Forward direction: report → other
        $this->assertDatabaseHas('raid_report_links', [
            'report_1' => $report->id,
            'report_2' => $other->id,
        ]);

        // Reverse direction: other → report
        $this->assertDatabaseHas('raid_report_links', [
            'report_1' => $other->id,
            'report_2' => $report->id,
        ]);
    }

    #[Test]
    public function update_creates_all_combinations_when_multiple_reports_selected(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $reportA = Report::factory()->withoutGuildTag()->create();
        $reportB = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $this->actingAs($user)->patch(route('raiding.reports.update', $report), [
            'links' => ['action' => 'create', 'link_ids' => [$reportA->id, $reportB->id]],
        ]);

        // All 6 directed pairs should exist
        foreach ([
            [$report->id, $reportA->id],
            [$reportA->id, $report->id],
            [$report->id, $reportB->id],
            [$reportB->id, $report->id],
            [$reportA->id, $reportB->id],
            [$reportB->id, $reportA->id],
        ] as [$r1, $r2]) {
            $this->assertDatabaseHas('raid_report_links', ['report_1' => $r1, 'report_2' => $r2]);
        }
    }

    #[Test]
    public function update_create_is_idempotent_for_already_linked_reports(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $other = Report::factory()->withoutGuildTag()->create();
        $report->linkedReports()->attach($other->id, ['created_by' => null]);
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->patch(route('raiding.reports.update', $report), [
            'links' => ['action' => 'create', 'link_ids' => [$other->id]],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('raid_report_links', 2); // forward + reverse, no duplicates
    }

    #[Test]
    public function update_create_extends_links_to_reports_already_linked_to_selected(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $reportA = Report::factory()->withoutGuildTag()->create();
        $reportB = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        // A is already linked to B
        $reportA->linkedReports()->attach($reportB->id, ['created_by' => $user->id]);
        $reportB->linkedReports()->attach($reportA->id, ['created_by' => $user->id]);

        // Link report to A only — it should also be linked to B transitively
        $this->actingAs($user)->patch(route('raiding.reports.update', $report), [
            'links' => ['action' => 'create', 'link_ids' => [$reportA->id]],
        ]);

        $this->assertDatabaseHas('raid_report_links', ['report_1' => $report->id, 'report_2' => $reportB->id]);
        $this->assertDatabaseHas('raid_report_links', ['report_1' => $reportB->id, 'report_2' => $report->id]);
    }

    #[Test]
    public function update_create_redirects_back_on_success(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $other = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->patch(route('raiding.reports.update', $report), [
            'links' => ['action' => 'create', 'link_ids' => [$other->id]],
        ]);

        $response->assertRedirect();
    }

    // ==================== links: delete ====================

    #[Test]
    public function update_delete_deletes_all_manual_links_bidirectionally(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $reportB = Report::factory()->withoutGuildTag()->create();
        $reportC = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        // Create manual bidirectional links: report ↔ B, report ↔ C
        $report->linkedReports()->attach($reportB->id, ['created_by' => $user->id]);
        $reportB->linkedReports()->attach($report->id, ['created_by' => $user->id]);
        $report->linkedReports()->attach($reportC->id, ['created_by' => $user->id]);
        $reportC->linkedReports()->attach($report->id, ['created_by' => $user->id]);

        $this->actingAs($user)->patch(route('raiding.reports.update', $report), [
            'links' => ['action' => 'delete', 'link_ids' => []],
        ]);

        // Forward links from report should be gone
        $this->assertDatabaseMissing('raid_report_links', ['report_1' => $report->id, 'report_2' => $reportB->id]);
        $this->assertDatabaseMissing('raid_report_links', ['report_1' => $report->id, 'report_2' => $reportC->id]);
        // Reverse links back to report should be gone
        $this->assertDatabaseMissing('raid_report_links', ['report_1' => $reportB->id, 'report_2' => $report->id]);
        $this->assertDatabaseMissing('raid_report_links', ['report_1' => $reportC->id, 'report_2' => $report->id]);
    }

    #[Test]
    public function update_delete_does_not_delete_auto_linked_reports(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $autoLinked = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        // Insert with created_at = null to simulate auto-linked rows (no Eloquent timestamps)
        DB::table('raid_report_links')->insert([
            ['report_1' => $report->id, 'report_2' => $autoLinked->id, 'created_by' => null, 'created_at' => null, 'updated_at' => null],
            ['report_1' => $autoLinked->id, 'report_2' => $report->id, 'created_by' => null, 'created_at' => null, 'updated_at' => null],
        ]);

        $this->actingAs($user)->patch(route('raiding.reports.update', $report), [
            'links' => ['action' => 'delete', 'link_ids' => []],
        ]);

        $this->assertDatabaseHas('raid_report_links', ['report_1' => $report->id, 'report_2' => $autoLinked->id]);
        $this->assertDatabaseHas('raid_report_links', ['report_1' => $autoLinked->id, 'report_2' => $report->id]);
    }

    #[Test]
    public function update_delete_is_a_no_op_when_no_manual_links_exist(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->patch(route('raiding.reports.update', $report), [
            'links' => ['action' => 'delete', 'link_ids' => []],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('raid_report_links', 0);
    }

    #[Test]
    public function update_delete_redirects_back_on_success(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->patch(route('raiding.reports.update', $report), [
            'links' => ['action' => 'delete', 'link_ids' => []],
        ]);

        $response->assertRedirect();
    }

    // ==================== loot_councillors ====================

    #[Test]
    public function update_loot_councillors_rejects_invalid_action(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();
        $character = Character::factory()->lootCouncillor()->create();

        $response = $this->actingAs($user)->patch(route('raiding.reports.update', $report), [
            'loot_councillors' => ['action' => 'invalid', 'character_ids' => [$character->id]],
        ]);

        $response->assertSessionHasErrors(['loot_councillors.action']);
    }

    #[Test]
    public function update_loot_councillors_create_rejects_character_without_loot_councillor_flag(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();
        $character = Character::factory()->create(['is_loot_councillor' => false]);

        $response = $this->actingAs($user)->patch(route('raiding.reports.update', $report), [
            'loot_councillors' => ['action' => 'create', 'character_ids' => [$character->id]],
        ]);

        $response->assertSessionHasErrors(['loot_councillors.character_ids.0']);
    }

    #[Test]
    public function update_loot_councillors_create_sets_is_loot_councillor_on_existing_pivot(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();
        $character = Character::factory()->lootCouncillor()->create();

        $report->characters()->attach($character->id, ['presence' => 1, 'is_loot_councillor' => false]);

        $this->actingAs($user)->patch(route('raiding.reports.update', $report), [
            'loot_councillors' => ['action' => 'create', 'character_ids' => [$character->id]],
        ]);

        $this->assertDatabaseHas('pivot_characters_raid_reports', [
            'raid_report_id' => $report->id,
            'character_id' => $character->id,
            'presence' => 1,
            'is_loot_councillor' => true,
        ]);
    }

    #[Test]
    public function update_loot_councillors_create_attaches_absent_character_with_presence_zero(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();
        $character = Character::factory()->lootCouncillor()->create();

        $this->actingAs($user)->patch(route('raiding.reports.update', $report), [
            'loot_councillors' => ['action' => 'create', 'character_ids' => [$character->id]],
        ]);

        $this->assertDatabaseHas('pivot_characters_raid_reports', [
            'raid_report_id' => $report->id,
            'character_id' => $character->id,
            'presence' => 0,
            'is_loot_councillor' => true,
        ]);
    }

    #[Test]
    public function update_loot_councillors_delete_sets_is_loot_councillor_false_for_present_character(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();
        $character = Character::factory()->lootCouncillor()->create();

        $report->characters()->attach($character->id, ['presence' => 1, 'is_loot_councillor' => true]);

        $this->actingAs($user)->patch(route('raiding.reports.update', $report), [
            'loot_councillors' => ['action' => 'delete', 'character_ids' => [$character->id]],
        ]);

        $this->assertDatabaseHas('pivot_characters_raid_reports', [
            'raid_report_id' => $report->id,
            'character_id' => $character->id,
            'presence' => 1,
            'is_loot_councillor' => false,
        ]);
    }

    #[Test]
    public function update_loot_councillors_delete_removes_pivot_row_for_absence_only_character(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();
        $character = Character::factory()->lootCouncillor()->create();

        $report->characters()->attach($character->id, ['presence' => 0, 'is_loot_councillor' => true]);

        $this->actingAs($user)->patch(route('raiding.reports.update', $report), [
            'loot_councillors' => ['action' => 'delete', 'character_ids' => [$character->id]],
        ]);

        $this->assertDatabaseMissing('pivot_characters_raid_reports', [
            'raid_report_id' => $report->id,
            'character_id' => $character->id,
        ]);
    }

    #[Test]
    public function update_loot_councillors_redirects_back_on_success(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();
        $character = Character::factory()->lootCouncillor()->create();

        $response = $this->actingAs($user)->patch(route('raiding.reports.update', $report), [
            'loot_councillors' => ['action' => 'create', 'character_ids' => [$character->id]],
        ]);

        $response->assertRedirect();
    }
}
