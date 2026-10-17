<?php

namespace Tests\Feature\Dashboard;

use App\Actions\Phase\ReorderPhases;
use App\Actions\Phase\UpdatePhase;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\Report;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Mockery\Matcher\MatcherInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Support\DashboardTestCase;

#[Group('raiding')]
class PhaseControllerTest extends DashboardTestCase
{
    #[Test]
    public function it_redirects_guests_to_login(): void
    {
        $response = $this->get(route('management.phases.index'));

        $response->assertRedirect(route('login'));
    }

    // ==================== index ====================

    #[Group('authorization')]
    #[Test]
    public function it_forbids_the_index_without_edit_datasets_permission(): void
    {
        $dashboardOnlyUser = User::factory()->withPermissions('view-officer-dashboard')->create();

        $response = $this->actingAs($dashboardOnlyUser)->get(route('management.phases.index'));

        $response->assertForbidden();
    }

    #[Group('happy-path')]
    #[Test]
    public function it_lists_phases_grouped_under_their_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Anniversary']);
        $phase = Phase::factory()->for($gameVersion)->create(['number' => '1.0', 'start_date' => now()->subMonth()]);
        Raid::factory()->for($phase)->create(['name' => 'Karazhan']);

        $response = $this->actingAs($this->officer)->get(route('management.phases.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Phases/Index')
            ->has('groups', 1)
            ->where('groups.0.game_version.data.title', 'Anniversary')
            ->where('groups.0.phases.data.0.name', 'Phase 1')
            ->where('groups.0.phases.data.0.is_current', true)
            ->where('groups.0.phases.data.0.raids_count', 1)
            ->where('groups.0.phases.data.0.links', [
                'edit' => route('management.phases.edit', $phase),
                'destroy' => route('management.phases.destroy', $phase),
            ])
            ->where('routes', [
                'create' => route('management.phases.create'),
                'reorder' => route('management.phases.reorder'),
            ])
        );
    }

    // ==================== create ====================

    #[Group('happy-path')]
    #[Test]
    public function it_renders_the_create_page_with_the_game_version_options(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Anniversary']);

        $response = $this->actingAs($this->officer)->get(route('management.phases.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Phases/Create')
            ->where('options.game_versions', [['value' => $gameVersion->id, 'label' => 'Anniversary']])
            ->where('gameVersionId', null)
            ->where('routes', [
                'index' => route('management.phases.index'),
                'store' => route('management.phases.store'),
            ])
        );
    }

    #[Test]
    public function it_preselects_the_game_version_given_in_the_query(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $response = $this->actingAs($this->officer)->get(route('management.phases.create', ['game_version' => $gameVersion->id]));

        $response->assertInertia(fn (Assert $page) => $page->where('gameVersionId', $gameVersion->id));
    }

    #[Test]
    public function it_ignores_an_unknown_game_version_in_the_query(): void
    {
        $response = $this->actingAs($this->officer)->get(route('management.phases.create', ['game_version' => 999999]));

        $response->assertInertia(fn (Assert $page) => $page->where('gameVersionId', null));
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_create_without_edit_datasets_permission(): void
    {
        $dashboardOnlyUser = User::factory()->withPermissions('view-officer-dashboard')->create();

        $response = $this->actingAs($dashboardOnlyUser)->get(route('management.phases.create'));

        $response->assertForbidden();
    }

    // ==================== store ====================

    #[Group('happy-path')]
    #[Test]
    public function it_stores_a_phase_and_redirects_to_its_edit_page(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $response = $this->actingAs($this->officer)->post(route('management.phases.store'), [
            'number' => '3.5',
            'description' => "Zul'Aman",
            'start_date' => '',
            'game_version_id' => $gameVersion->id,
        ]);

        $phase = Phase::sole();
        $response->assertRedirect($this->editUrl($phase));
        $response->assertSessionHas('success', 'Added Phase 3.5.');
        $this->assertSame("Zul'Aman", $phase->description);
        $this->assertSame($gameVersion->id, $phase->game_version_id);
    }

    #[Test]
    public function it_stores_a_start_date_in_server_time(): void
    {
        $this->actingAs($this->officer)->post(route('management.phases.store'), [
            'number' => '1',
            'description' => 'Karazhan',
            'start_date' => '2026-10-23T19:00',
        ]);

        $this->assertTrue(Phase::sole()->start_date->equalTo(Carbon::create(2026, 10, 23, 19, 0, 0, 'Europe/Paris')));
    }

    #[Test]
    public function it_stores_a_phase_without_a_game_version_or_start_date(): void
    {
        $this->actingAs($this->officer)->post(route('management.phases.store'), [
            'number' => '1',
            'description' => 'Karazhan',
            'start_date' => '',
            'game_version_id' => '',
        ]);

        $phase = Phase::sole();
        $this->assertNull($phase->start_date);
        $this->assertNull($phase->game_version_id);
    }

    #[Group('validation')]
    #[Test]
    public function it_requires_a_number_and_description(): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.phases.store'), [
            'number' => '',
            'description' => '',
        ]);

        $response->assertInvalid([
            'number' => 'Enter the phase number.',
            'description' => 'Enter a description for the phase.',
        ]);
        $this->assertDatabaseCount('phases', 0);
    }

    #[Group('validation')]
    #[TestWith(['10', 'The phase number field must be between 0 and 9.9.'])]
    #[TestWith(['-1', 'The phase number field must be between 0 and 9.9.'])]
    #[TestWith(['1.25', 'The phase number field must have 0-1 decimal places.'])]
    #[Test]
    public function it_rejects_a_phase_number_the_column_cannot_hold(string $number, string $message): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.phases.store'), [
            'number' => $number,
            'description' => 'Too far',
        ]);

        $response->assertInvalid(['number' => $message]);
        $this->assertDatabaseCount('phases', 0);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_number_already_used_in_the_same_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create();
        Phase::factory()->for($gameVersion)->create(['number' => '2.0']);

        $response = $this->actingAs($this->officer)->post(route('management.phases.store'), [
            'number' => '2',
            'description' => 'Second',
            'game_version_id' => $gameVersion->id,
        ]);

        $response->assertInvalid(['number' => 'Another phase in this game version already has that number.']);
        $this->assertDatabaseCount('phases', 1);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_number_already_used_by_another_phase_without_a_game_version(): void
    {
        Phase::factory()->create(['number' => '2.0', 'game_version_id' => null]);

        $response = $this->actingAs($this->officer)->post(route('management.phases.store'), [
            'number' => '2',
            'description' => 'Second',
        ]);

        $response->assertInvalid(['number' => 'Another phase in this game version already has that number.']);
        $this->assertDatabaseCount('phases', 1);
    }

    #[Test]
    public function it_allows_a_number_another_game_version_uses(): void
    {
        Phase::factory()->for(GameVersion::factory())->create(['number' => '2.0']);

        $response = $this->actingAs($this->officer)->post(route('management.phases.store'), [
            'number' => '2',
            'description' => 'Second',
            'game_version_id' => GameVersion::factory()->create()->id,
        ]);

        $response->assertValid();
        $this->assertDatabaseCount('phases', 2);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_an_unknown_game_version(): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.phases.store'), [
            'number' => '1',
            'description' => 'First',
            'game_version_id' => 999999,
        ]);

        $response->assertInvalid(['game_version_id' => 'Choose one of the listed game versions.']);
        $this->assertDatabaseCount('phases', 0);
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_store_without_edit_datasets_permission(): void
    {
        $dashboardOnlyUser = User::factory()->withPermissions('view-officer-dashboard')->create();

        $response = $this->actingAs($dashboardOnlyUser)->post(route('management.phases.store'), [
            'number' => '1',
            'description' => 'First',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('phases', 0);
    }

    // ==================== edit ====================

    #[Group('happy-path')]
    #[Test]
    public function it_renders_the_edit_page_with_the_phase_its_raids_and_options(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Anniversary']);
        $phase = Phase::factory()->for($gameVersion)->create(['number' => '3.5', 'description' => "Zul'Aman"]);
        Raid::factory()->for($phase)->create(['name' => "Zul'Aman"]);

        $response = $this->actingAs($this->officer)->get($this->editUrl($phase));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Phases/Edit')
            ->where('phase.data.name', 'Phase 3.5')
            ->where('phase.data.description', "Zul'Aman")
            ->where('phase.data.game_version.id', $gameVersion->id)
            ->has('phase.data.raids', 1)
            ->missing('phase.data.links')
            ->where('options.game_versions', [['value' => $gameVersion->id, 'label' => 'Anniversary']])
            ->where('routes', [
                'index' => route('management.phases.index'),
                'update' => route('management.phases.update', $phase),
            ])
            ->where('canEdit', true)
        );
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_edit_without_edit_datasets_permission(): void
    {
        $dashboardOnlyUser = User::factory()->withPermissions('view-officer-dashboard')->create();

        $response = $this->actingAs($dashboardOnlyUser)->get($this->editUrl(Phase::factory()->create()));

        $response->assertForbidden();
    }

    // ==================== edit lock ====================

    #[Test]
    public function it_shows_the_edit_page_read_only_to_a_second_officer_while_the_lock_is_held(): void
    {
        $phase = Phase::factory()->create();
        $holder = User::factory()->officer()->create();
        $phase->acquireEditLock($holder);

        $response = $this->actingAs($this->officer)->get($this->editUrl($phase));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('canEdit', false)
            ->where('editor', $holder->display_name)
        );
    }

    #[Test]
    public function it_rejects_an_update_while_another_officer_holds_the_edit_lock(): void
    {
        $phase = Phase::factory()->create();
        $phase->acquireEditLock(User::factory()->officer()->create());
        UpdatePhase::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($phase))
            ->patch(route('management.phases.update', $phase), ['description' => 'Mine']);

        $response->assertSessionHasErrors(['edit_lock' => 'Someone else is editing this phase. Your change was not saved.']);
    }

    // ==================== update ====================

    #[Group('happy-path')]
    #[Test]
    public function it_updates_a_phase_and_redirects_back(): void
    {
        $phase = Phase::factory()->create(['number' => '1.0']);
        $this->expectUpdate($phase, ['description' => 'Karazhan']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($phase))
            ->patch(route('management.phases.update', $phase), ['description' => 'Karazhan']);

        $response->assertRedirect($this->editUrl($phase));
        $response->assertSessionHas('success', 'Saved changes to Phase 1.');
    }

    #[Test]
    public function it_saves_an_autosave_without_flashing_a_message(): void
    {
        $phase = Phase::factory()->create();
        $this->expectUpdate($phase, ['description' => 'Karazhan']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($phase))
            ->withHeader('X-Autosave', '1')
            ->patch(route('management.phases.update', $phase), ['description' => 'Karazhan']);

        $response->assertRedirect($this->editUrl($phase));
        $response->assertSessionMissing('success');
    }

    #[Test]
    public function it_passes_a_cleared_start_date_as_null(): void
    {
        $phase = Phase::factory()->started()->create();
        $this->expectUpdate($phase, ['start_date' => null]);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($phase))
            ->patchJson(route('management.phases.update', $phase), ['start_date' => null]);

        $response->assertValid();
    }

    #[Test]
    public function it_allows_an_update_that_keeps_the_same_number(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phase = Phase::factory()->for($gameVersion)->create(['number' => '2.0']);
        $this->expectUpdate($phase, ['number' => '2']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($phase))
            ->patch(route('management.phases.update', $phase), ['number' => '2']);

        $response->assertValid();
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_number_another_phase_in_the_game_version_uses(): void
    {
        $gameVersion = GameVersion::factory()->create();
        Phase::factory()->for($gameVersion)->create(['number' => '2.0']);
        $phase = Phase::factory()->for($gameVersion)->create(['number' => '3.0']);
        UpdatePhase::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($phase))
            ->patch(route('management.phases.update', $phase), ['number' => '2']);

        $response->assertInvalid(['number' => 'Another phase in this game version already has that number.']);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_moving_a_phase_to_a_game_version_that_already_uses_its_number(): void
    {
        $target = GameVersion::factory()->create();
        Phase::factory()->for($target)->create(['number' => '2.0']);
        $phase = Phase::factory()->for(GameVersion::factory())->create(['number' => '2.0']);
        UpdatePhase::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($phase))
            ->patch(route('management.phases.update', $phase), ['game_version_id' => $target->id]);

        $response->assertInvalid(['game_version_id' => 'That game version already has a phase with this number.']);
    }

    #[Test]
    public function it_allows_moving_a_phase_to_a_game_version_where_its_number_is_free(): void
    {
        $target = GameVersion::factory()->create();
        Phase::factory()->for($target)->create(['number' => '1.0']);
        $phase = Phase::factory()->for(GameVersion::factory())->create(['number' => '2.0']);
        $this->expectUpdate($phase, ['game_version_id' => $target->id]);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($phase))
            ->patch(route('management.phases.update', $phase), ['game_version_id' => $target->id]);

        $response->assertValid();
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_number_or_description_sent_as_null(): void
    {
        $phase = Phase::factory()->create();
        UpdatePhase::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($phase))
            ->patchJson(route('management.phases.update', $phase), ['number' => null, 'description' => null]);

        $response->assertInvalid([
            'number' => 'Enter the phase number.',
            'description' => 'Enter a description for the phase.',
        ]);
    }

    #[Test]
    public function it_accepts_an_update_from_the_edit_lock_holder(): void
    {
        $phase = Phase::factory()->create();
        $phase->acquireEditLock($this->officer);
        $this->expectUpdate($phase, ['description' => 'Mine']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($phase))
            ->patch(route('management.phases.update', $phase), ['description' => 'Mine']);

        $response->assertSessionHasNoErrors();
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_update_without_edit_datasets_permission(): void
    {
        $phase = Phase::factory()->create();
        $dashboardOnlyUser = User::factory()->withPermissions('view-officer-dashboard')->create();
        UpdatePhase::shouldNotRun();

        $response = $this->actingAs($dashboardOnlyUser)->patch(route('management.phases.update', $phase), ['description' => 'Nope']);

        $response->assertForbidden();
    }

    // ==================== reorder ====================

    #[Group('happy-path')]
    #[Test]
    public function it_saves_the_order_of_a_game_versions_unscheduled_phases(): void
    {
        $gameVersion = GameVersion::factory()->create();
        [$first, $second] = Phase::factory()->for($gameVersion)->unscheduled()->count(2)->create();
        Phase::factory()->for($gameVersion)->started()->create();
        ReorderPhases::shouldRun()->once()->with([$second->id, $first->id]);

        $response = $this->actingAs($this->officer)
            ->from(route('management.phases.index'))
            ->patchJson(route('management.phases.reorder'), [
                'game_version_id' => $gameVersion->id,
                'phase_ids' => [$second->id, $first->id],
            ]);

        $response->assertValid();
    }

    #[Test]
    public function it_saves_the_order_of_unscheduled_phases_without_a_game_version(): void
    {
        [$first, $second] = Phase::factory()->unscheduled()->count(2)->create(['game_version_id' => null]);
        ReorderPhases::shouldRun()->once()->with([$second->id, $first->id]);

        $response = $this->actingAs($this->officer)
            ->from(route('management.phases.index'))
            ->patchJson(route('management.phases.reorder'), [
                'game_version_id' => null,
                'phase_ids' => [$second->id, $first->id],
            ]);

        $response->assertValid();
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_an_order_that_leaves_out_an_unscheduled_phase(): void
    {
        $gameVersion = GameVersion::factory()->create();
        [$first] = Phase::factory()->for($gameVersion)->unscheduled()->count(2)->create();
        ReorderPhases::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from(route('management.phases.index'))
            ->patchJson(route('management.phases.reorder'), [
                'game_version_id' => $gameVersion->id,
                'phase_ids' => [$first->id],
            ]);

        $response->assertInvalid([
            'phase_ids' => 'The unscheduled phases have changed since this page loaded. Reload the page and try again.',
        ]);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_an_order_that_includes_a_scheduled_phase(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $unscheduled = Phase::factory()->for($gameVersion)->unscheduled()->create();
        $scheduled = Phase::factory()->for($gameVersion)->started()->create();
        ReorderPhases::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from(route('management.phases.index'))
            ->patchJson(route('management.phases.reorder'), [
                'game_version_id' => $gameVersion->id,
                'phase_ids' => [$unscheduled->id, $scheduled->id],
            ]);

        $response->assertInvalid([
            'phase_ids' => 'The unscheduled phases have changed since this page loaded. Reload the page and try again.',
        ]);
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_reorder_without_edit_datasets_permission(): void
    {
        $dashboardOnlyUser = User::factory()->withPermissions('view-officer-dashboard')->create();
        ReorderPhases::shouldNotRun();

        $response = $this->actingAs($dashboardOnlyUser)->patchJson(route('management.phases.reorder'), [
            'game_version_id' => null,
            'phase_ids' => [],
        ]);

        $response->assertForbidden();
    }

    // ==================== destroy ====================

    #[Group('happy-path')]
    #[Test]
    public function it_deletes_a_phase_that_is_not_in_use(): void
    {
        $phase = Phase::factory()->create(['number' => '4.0']);

        $response = $this->actingAs($this->officer)->delete(route('management.phases.destroy', $phase));

        $response->assertRedirect(route('management.phases.index'));
        $response->assertSessionHas('success', 'Deleted Phase 4.');
        $this->assertModelMissing($phase);
    }

    #[Test]
    public function it_refuses_to_delete_a_phase_with_raids(): void
    {
        $phase = Phase::factory()->create(['number' => '1.0']);
        Raid::factory()->for($phase)->create();

        $response = $this->actingAs($this->officer)
            ->from(route('management.phases.index'))
            ->delete(route('management.phases.destroy', $phase));

        $response->assertRedirect(route('management.phases.index'));
        $response->assertSessionHas('error', 'Phase 1 is still linked to other records and cannot be deleted.');
        $this->assertModelExists($phase);
    }

    #[Test]
    public function it_refuses_to_delete_a_phase_with_reports(): void
    {
        $phase = Phase::factory()->create(['number' => '1.0']);
        Report::withoutEvents(fn () => Report::factory()->create(['phase_id' => $phase->id]));

        $response = $this->actingAs($this->officer)
            ->from(route('management.phases.index'))
            ->delete(route('management.phases.destroy', $phase));

        $response->assertSessionHas('error', 'Phase 1 is still linked to other records and cannot be deleted.');
        $this->assertModelExists($phase);
    }

    #[Test]
    public function it_refuses_to_delete_a_phase_another_officer_is_editing(): void
    {
        $phase = Phase::factory()->create(['number' => '1.0']);
        $phase->acquireEditLock(User::factory()->officer()->create());

        $response = $this->actingAs($this->officer)
            ->from(route('management.phases.index'))
            ->delete(route('management.phases.destroy', $phase));

        $response->assertSessionHas('error', "Someone is editing Phase 1, so it can't be deleted right now.");
        $this->assertModelExists($phase);
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_destroy_without_edit_datasets_permission(): void
    {
        $phase = Phase::factory()->create();
        $dashboardOnlyUser = User::factory()->withPermissions('view-officer-dashboard')->create();

        $response = $this->actingAs($dashboardOnlyUser)->delete(route('management.phases.destroy', $phase));

        $response->assertForbidden();
        $this->assertModelExists($phase);
    }

    // ==================== helpers ====================

    private function editUrl(Phase $phase): string
    {
        return route('management.phases.edit', $phase);
    }

    /**
     * Fake UpdatePhase and expect it to run once, for the given phase, with
     * exactly the given validated data.
     *
     * @param  array<string, mixed>  $data
     */
    private function expectUpdate(Phase $phase, array $data): void
    {
        UpdatePhase::shouldRun()
            ->once()
            ->with($this->isPhase($phase), $this->equalTo($data));
    }

    /**
     * Match the route-bound copy of the given phase in a faked action's arguments.
     */
    private function isPhase(Phase $phase): MatcherInterface
    {
        return Mockery::on(fn (Phase $given): bool => $given->is($phase));
    }
}
