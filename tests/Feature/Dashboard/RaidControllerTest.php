<?php

namespace Tests\Feature\Dashboard;

use App\Actions\Raid\UpdateRaid;
use App\Contracts\Actions\GameVersion\SharesCurrentGameVersionSession;
use App\Enums\BannerPosition;
use App\Models\Boss;
use App\Models\Event;
use App\Models\GameVersion;
use App\Models\Item;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Cache\Events\CacheFlushed;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Mockery\Matcher\MatcherInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Support\DashboardTestCase;

#[Group('raiding')]
class RaidControllerTest extends DashboardTestCase
{
    #[Group('authorization')]
    #[Test]
    public function it_redirects_guests_to_login(): void
    {
        $response = $this->get(route('management.raids.index'));

        $response->assertRedirect(route('login'));
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_the_index_without_edit_datasets_permission(): void
    {
        $dashboardOnlyUser = User::factory()->withPermissions('view-officer-dashboard')->create();

        $response = $this->actingAs($dashboardOnlyUser)->get(route('management.raids.index'));

        $response->assertForbidden();
    }

    #[Group('happy-path')]
    #[Test]
    public function it_lists_raids_grouped_under_their_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Anniversary']);
        $phase = Phase::factory()->for($gameVersion)->create(['number' => '1.0']);
        $raid = Raid::factory()->for($phase)->create(['name' => 'Karazhan']);

        $response = $this->actingAs($this->officer)->get(route('management.raids.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Raids/Index')
            ->has('groups', 1)
            ->where('groups.0.game_version.data.title', 'Anniversary')
            ->where('groups.0.raids.data.0.name', 'Karazhan')
            ->where('groups.0.raids.data.0.phase.name', 'Phase 1')
            ->where('groups.0.raids.data.0.bosses_count', 0)
            ->where('groups.0.raids.data.0.past_events_count', 0)
            ->where('groups.0.raids.data.0.links', [
                'edit' => route('management.raids.edit', $raid),
                'destroy' => route('management.raids.destroy', $raid),
            ])
            ->where('routes', ['create' => route('management.raids.create')])
        );
    }

    #[Test]
    public function it_lists_every_version_whatever_the_session_holds(): void
    {
        $era = GameVersion::factory()->create(['title' => 'Era', 'release_date' => '2019-08-27']);
        GameVersion::factory()->create(['title' => 'Anniversary', 'release_date' => '2026-02-06']);

        $response = $this->actingAs($this->officer)
            ->withSession([SharesCurrentGameVersionSession::SESSION_KEY => $era->slug])
            ->get(route('management.raids.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('groups', 2)
            ->where('groups.0.game_version.data.title', 'Anniversary')
            ->where('groups.1.game_version.data.title', 'Era')
        );
    }

    #[Test]
    public function it_lists_raids_without_lazy_loading(): void
    {
        Storage::fake('public');
        $phase = Phase::factory()->for(GameVersion::factory())->create();
        Raid::factory()->count(2)->for($phase)->withBanner()->create();

        config(['media-library.force_lazy_loading' => false]);
        Model::preventLazyLoading();

        try {
            $response = $this->withoutExceptionHandling()->actingAs($this->officer)->get(route('management.raids.index'));

            $response->assertInertia(fn (Assert $page) => $page->whereNot('groups.0.raids.data.0.banner_url', null));
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    // ==================== create ====================

    #[Group('happy-path')]
    #[Test]
    public function it_renders_the_create_page_with_the_grouped_phase_options(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Anniversary']);
        $phase = Phase::factory()->for($gameVersion)->create(['number' => '1.0']);

        $response = $this->actingAs($this->officer)->get(route('management.raids.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Raids/Create')
            ->where('phaseId', null)
            ->where('options.phases', [['label' => 'Anniversary', 'options' => [['value' => $phase->id, 'label' => 'Phase 1']]]])
            ->where('options.banner_positions', BannerPosition::options())
            ->where('steps', [['value' => 'bosses', 'label' => 'Bosses', 'component' => 'BossesSection']])
            ->where('routes', [
                'index' => route('management.raids.index'),
                'store' => route('management.raids.store'),
            ])
        );
    }

    #[Test]
    public function it_preselects_the_phase_given_in_the_query(): void
    {
        $phase = Phase::factory()->create();

        $response = $this->actingAs($this->officer)->get(route('management.raids.create', ['phase' => $phase->id]));

        $response->assertInertia(fn (Assert $page) => $page->where('phaseId', $phase->id));
    }

    #[Test]
    public function it_ignores_an_unknown_phase_in_the_query(): void
    {
        $response = $this->actingAs($this->officer)->get(route('management.raids.create', ['phase' => 999999]));

        $response->assertInertia(fn (Assert $page) => $page->where('phaseId', null));
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_create_without_edit_datasets_permission(): void
    {
        $dashboardOnlyUser = User::factory()->withPermissions('view-officer-dashboard')->create();

        $response = $this->actingAs($dashboardOnlyUser)->get(route('management.raids.create'));

        $response->assertForbidden();
    }

    // ==================== store ====================

    #[Group('happy-path')]
    #[Test]
    public function it_stores_a_raid_and_continues_to_its_bosses_step(): void
    {
        $phase = Phase::factory()->for(GameVersion::factory())->create();

        $response = $this->actingAs($this->officer)->post(route('management.raids.store'), [
            'name' => 'Karazhan',
            'difficulty' => 'normal',
            'phase_id' => $phase->id,
            'color' => '#8B7ED0',
            'max_players' => '10',
            'max_loot_councillors' => '3',
        ]);

        $raid = Raid::sole();
        $response->assertRedirect(route('management.raids.setup', [$raid, 'bosses']));
        $response->assertSessionHas('success', 'Added Karazhan.');
        $this->assertSame('Normal', $raid->difficulty);
        $this->assertSame($phase->id, $raid->phase_id);
        $this->assertSame('8b7ed0', $raid->color);
        $this->assertSame(10, $raid->max_players);
        $this->assertSame(3, $raid->max_loot_councillors);
    }

    #[Test]
    public function it_stores_blank_optional_fields_as_null(): void
    {
        $phase = Phase::factory()->create();

        $this->actingAs($this->officer)->post(route('management.raids.store'), [
            'name' => 'Karazhan',
            'difficulty' => 'Normal',
            'phase_id' => $phase->id,
            'color' => '',
            'max_players' => '',
            'max_loot_councillors' => '',
        ]);

        $raid = Raid::sole();
        $this->assertNull($raid->color);
        $this->assertNull($raid->max_players);
        $this->assertNull($raid->max_loot_councillors);
    }

    #[Group('validation')]
    #[Test]
    public function it_requires_a_name_difficulty_and_phase(): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.raids.store'), [
            'name' => '',
            'difficulty' => '',
            'phase_id' => '',
        ]);

        $response->assertInvalid([
            'name' => 'Enter the raid name.',
            'difficulty' => 'Enter the raid difficulty, for example Normal.',
            'phase_id' => 'Choose which phase the raid belongs to.',
        ]);
        $this->assertDatabaseCount('raids', 0);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_an_unknown_phase(): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.raids.store'), [
            'name' => 'Karazhan',
            'difficulty' => 'Normal',
            'phase_id' => 999999,
        ]);

        $response->assertInvalid(['phase_id' => 'Choose one of the listed phases.']);
        $this->assertDatabaseCount('raids', 0);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_name_and_difficulty_already_used_in_the_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Anniversary']);
        Raid::factory()->for(Phase::factory()->for($gameVersion))->create(['name' => 'Karazhan', 'difficulty' => 'Normal']);
        $phase = Phase::factory()->for($gameVersion)->create();

        $response = $this->actingAs($this->officer)->post(route('management.raids.store'), [
            'name' => 'Karazhan',
            'difficulty' => 'normal',
            'phase_id' => $phase->id,
        ]);

        $response->assertInvalid(['name' => 'Karazhan (Normal) already exists in Anniversary.']);
        $this->assertDatabaseCount('raids', 1);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_name_and_difficulty_already_used_among_raids_with_no_game_version(): void
    {
        Raid::factory()->for(Phase::factory()->state(['game_version_id' => null]))->create(['name' => 'Karazhan', 'difficulty' => 'Normal']);
        $phase = Phase::factory()->create(['game_version_id' => null]);

        $response = $this->actingAs($this->officer)->post(route('management.raids.store'), [
            'name' => 'Karazhan',
            'difficulty' => 'Normal',
            'phase_id' => $phase->id,
        ]);

        $response->assertInvalid(['name' => 'Karazhan (Normal) already exists among raids with no game version.']);
        $this->assertDatabaseCount('raids', 1);
    }

    #[Test]
    public function it_allows_a_name_and_difficulty_another_game_version_uses(): void
    {
        Raid::factory()->for(Phase::factory()->for(GameVersion::factory()))->create(['name' => 'Karazhan', 'difficulty' => 'Normal']);
        $phase = Phase::factory()->for(GameVersion::factory())->create();

        $response = $this->actingAs($this->officer)->post(route('management.raids.store'), [
            'name' => 'Karazhan',
            'difficulty' => 'Normal',
            'phase_id' => $phase->id,
        ]);

        $response->assertValid();
        $this->assertDatabaseCount('raids', 2);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_more_loot_councillors_than_players(): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.raids.store'), [
            'name' => 'Karazhan',
            'difficulty' => 'Normal',
            'phase_id' => Phase::factory()->create()->id,
            'max_players' => '10',
            'max_loot_councillors' => '11',
        ]);

        $response->assertInvalid(['max_loot_councillors' => "The maximum loot councillors can't be more than the maximum players."]);
        $this->assertDatabaseCount('raids', 0);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_maximum_player_count_above_forty(): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.raids.store'), [
            'name' => 'Karazhan',
            'difficulty' => 'Normal',
            'phase_id' => Phase::factory()->create()->id,
            'max_players' => '41',
        ]);

        $response->assertInvalid(['max_players' => 'The maximum players field must be between 1 and 40.']);
        $this->assertDatabaseCount('raids', 0);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_colour_that_is_not_a_six_digit_hex_code(): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.raids.store'), [
            'name' => 'Karazhan',
            'difficulty' => 'Normal',
            'phase_id' => Phase::factory()->create()->id,
            'color' => '#8B7',
        ]);

        $response->assertInvalid(['color' => 'The colour must be a six-digit hex code, for example #8B7ED0.']);
        $this->assertDatabaseCount('raids', 0);
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_store_without_edit_datasets_permission(): void
    {
        $dashboardOnlyUser = User::factory()->withPermissions('view-officer-dashboard')->create();

        $response = $this->actingAs($dashboardOnlyUser)->post(route('management.raids.store'), [
            'name' => 'Karazhan',
            'difficulty' => 'Normal',
            'phase_id' => Phase::factory()->create()->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('raids', 0);
    }

    // ==================== edit ====================

    #[Group('happy-path')]
    #[Test]
    public function it_renders_the_edit_page_with_the_raid_its_bosses_and_options(): void
    {
        $phase = Phase::factory()->for(GameVersion::factory())->create(['number' => '1.0']);
        $raid = Raid::factory()->for($phase)->create(['name' => 'Karazhan']);
        Boss::factory()->for($raid)->create(['name' => 'Attumen the Huntsman']);

        $response = $this->actingAs($this->officer)->get($this->editUrl($raid));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Raids/Edit')
            ->where('raid.data.name', 'Karazhan')
            ->where('raid.data.phase_id', $phase->id)
            ->where('raid.data.phase.name', 'Phase 1')
            ->where('raid.data.bosses.0.name', 'Attumen the Huntsman')
            ->missing('raid.data.links')
            ->where('raid.media.banner.accepts', ['image/jpeg', 'image/png', 'image/webp'])
            ->has('options.phases', 1)
            ->where('steps', [[
                'value' => 'bosses',
                'label' => 'Bosses',
                'component' => 'BossesSection',
                'href' => route('management.raids.setup', [$raid, 'bosses']),
            ]])
            ->where('routes', [
                'index' => route('management.raids.index'),
                'update' => route('management.raids.update', $raid),
                'review' => route('management.raids.review', $raid),
            ])
            ->where('canEdit', true)
        );
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_edit_without_edit_datasets_permission(): void
    {
        $dashboardOnlyUser = User::factory()->withPermissions('view-officer-dashboard')->create();

        $response = $this->actingAs($dashboardOnlyUser)->get($this->editUrl(Raid::factory()->create()));

        $response->assertForbidden();
    }

    // ==================== edit lock ====================

    #[Test]
    public function it_shows_the_edit_page_read_only_to_a_second_officer_while_the_lock_is_held(): void
    {
        $raid = Raid::factory()->create();
        $holder = User::factory()->officer()->create();
        $raid->acquireEditLock($holder);

        $response = $this->actingAs($this->officer)->get($this->editUrl($raid));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('canEdit', false)
            ->where('editor', $holder->display_name)
        );
    }

    #[Test]
    public function it_rejects_an_update_while_another_officer_holds_the_edit_lock(): void
    {
        $raid = Raid::factory()->create();
        $raid->acquireEditLock(User::factory()->officer()->create());
        UpdateRaid::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($raid))
            ->patch(route('management.raids.update', $raid), ['name' => 'Mine']);

        $response->assertSessionHasErrors(['edit_lock' => 'Someone else is editing this raid. Your change was not saved.']);
    }

    // ==================== setup ====================

    #[Group('happy-path')]
    #[Test]
    public function it_renders_the_bosses_step(): void
    {
        $raid = Raid::factory()->create();
        Boss::factory()->for($raid)->count(2)->create();

        $response = $this->actingAs($this->officer)->get(route('management.raids.setup', [$raid, 'bosses']));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Raids/Setup')
            ->has('raid.data.bosses', 2)
            ->where('step', ['value' => 'bosses', 'label' => 'Bosses', 'component' => 'BossesSection'])
            ->where('previousStep', null)
            ->where('nextStep', null)
            ->where('returnToReview', false)
            ->where('routes', [
                'update' => route('management.raids.update', $raid),
                'edit' => route('management.raids.edit', $raid),
                'review' => route('management.raids.review', $raid),
                'previous' => route('management.raids.edit', $raid),
                'next' => route('management.raids.review', $raid),
            ])
            ->where('canEdit', true)
        );
    }

    #[Test]
    public function it_returns_to_review_from_a_setup_step_opened_from_review(): void
    {
        $raid = Raid::factory()->create();

        $response = $this->actingAs($this->officer)->get(route('management.raids.setup', [$raid, 'bosses', 'review' => 1]));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Raids/Setup')
            ->where('returnToReview', true)
            ->where('routes.next', route('management.raids.review', $raid))
        );
    }

    #[Test]
    public function it_shows_a_setup_step_read_only_to_a_second_officer_while_the_lock_is_held(): void
    {
        $raid = Raid::factory()->create();
        $holder = User::factory()->officer()->create();
        $raid->acquireEditLock($holder);

        $response = $this->actingAs($this->officer)->get(route('management.raids.setup', [$raid, 'bosses']));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Raids/Setup')
            ->where('canEdit', false)
            ->where('editor', $holder->display_name)
        );
    }

    #[Test]
    public function it_returns_404_for_an_unknown_setup_step(): void
    {
        $raid = Raid::factory()->create();

        $response = $this->actingAs($this->officer)->get("/manage/raids/{$raid->id}/setup/loot");

        $response->assertNotFound();
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_setup_without_edit_datasets_permission(): void
    {
        $dashboardOnlyUser = User::factory()->withPermissions('view-officer-dashboard')->create();

        $response = $this->actingAs($dashboardOnlyUser)->get(route('management.raids.setup', [Raid::factory()->create(), 'bosses']));

        $response->assertForbidden();
    }

    // ==================== review ====================

    #[Group('happy-path')]
    #[Test]
    public function it_renders_the_review_page(): void
    {
        $raid = Raid::factory()->create(['name' => 'Karazhan']);
        Boss::factory()->for($raid)->create(['name' => 'Moroes']);

        $response = $this->actingAs($this->officer)->get(route('management.raids.review', $raid));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Raids/Review')
            ->where('raid.data.name', 'Karazhan')
            ->where('raid.data.bosses.0.name', 'Moroes')
            ->where('options.banner_positions', BannerPosition::options())
            ->has('steps', 1)
            ->where('routes', [
                'index' => route('management.raids.index'),
                'edit' => route('management.raids.edit', $raid),
                'review' => route('management.raids.review', $raid),
            ])
        );
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_review_without_edit_datasets_permission(): void
    {
        $dashboardOnlyUser = User::factory()->withPermissions('view-officer-dashboard')->create();

        $response = $this->actingAs($dashboardOnlyUser)->get(route('management.raids.review', Raid::factory()->create()));

        $response->assertForbidden();
    }

    // ==================== update ====================

    #[Group('happy-path')]
    #[Test]
    public function it_updates_a_raid_and_redirects_back(): void
    {
        $raid = Raid::factory()->create(['name' => 'Karazhan']);
        $this->expectUpdate($raid, ['max_players' => '10']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($raid))
            ->patch(route('management.raids.update', $raid), ['max_players' => '10']);

        $response->assertRedirect($this->editUrl($raid));
        $response->assertSessionHas('success', 'Saved changes to Karazhan.');
    }

    #[Test]
    public function it_saves_an_autosave_without_flashing_a_message(): void
    {
        $raid = Raid::factory()->create();
        $this->expectUpdate($raid, ['max_players' => '25']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($raid))
            ->withHeader('X-Autosave', '1')
            ->patch(route('management.raids.update', $raid), ['max_players' => '25']);

        $response->assertRedirect($this->editUrl($raid));
        $response->assertSessionMissing('success');
    }

    #[Test]
    public function it_passes_blank_optional_fields_as_null(): void
    {
        $raid = Raid::factory()->create();
        $this->expectUpdate($raid, ['color' => null, 'max_players' => null, 'max_loot_councillors' => null]);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($raid))
            ->patch(route('management.raids.update', $raid), ['color' => '', 'max_players' => '', 'max_loot_councillors' => '']);

        $response->assertValid();
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_name_or_difficulty_sent_as_null(): void
    {
        $raid = Raid::factory()->create();
        UpdateRaid::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($raid))
            ->patchJson(route('management.raids.update', $raid), ['name' => null, 'difficulty' => null]);

        $response->assertInvalid([
            'name' => 'Enter the raid name.',
            'difficulty' => 'Enter the raid difficulty, for example Normal.',
        ]);
    }

    #[Test]
    public function it_allows_an_update_that_keeps_its_own_name_and_difficulty(): void
    {
        $raid = Raid::factory()->create(['name' => 'Karazhan', 'difficulty' => 'Normal']);
        $this->expectUpdate($raid, ['name' => 'Karazhan', 'difficulty' => 'Normal']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($raid))
            ->patch(route('management.raids.update', $raid), ['name' => 'Karazhan', 'difficulty' => 'Normal']);

        $response->assertValid();
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_an_autosave_that_changes_only_the_difficulty_into_a_clash(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Anniversary']);
        $phase = Phase::factory()->for($gameVersion)->create();
        Raid::factory()->for($phase)->create(['name' => 'Karazhan', 'difficulty' => 'Heroic']);
        $raid = Raid::factory()->for($phase)->create(['name' => 'Karazhan', 'difficulty' => 'Normal']);
        UpdateRaid::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($raid))
            ->withHeader('X-Autosave', '1')
            ->patch(route('management.raids.update', $raid), ['difficulty' => 'heroic']);

        $response->assertInvalid(['difficulty' => 'Karazhan (Heroic) already exists in Anniversary.']);
    }

    #[Group('validation')]
    #[Test]
    #[DataProvider('linkedRecords')]
    public function it_rejects_moving_a_used_raid_to_another_game_versions_phase(string $link): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Anniversary']);
        $raid = Raid::factory()->for(Phase::factory()->for($gameVersion))->create();
        $this->linkRecord($raid, $link);
        $otherPhase = Phase::factory()->for(GameVersion::factory())->create();
        UpdateRaid::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($raid))
            ->patch(route('management.raids.update', $raid), ['phase_id' => $otherPhase->id]);

        $response->assertInvalid(['phase_id' => 'This raid already has bosses, events or items, so it can only move to a phase in Anniversary.']);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_moving_a_used_raid_with_no_game_version_to_a_versioned_phase(): void
    {
        $raid = Raid::factory()->for(Phase::factory())->create();
        Boss::factory()->for($raid)->create();
        $versionedPhase = Phase::factory()->for(GameVersion::factory())->create();
        UpdateRaid::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($raid))
            ->patch(route('management.raids.update', $raid), ['phase_id' => $versionedPhase->id]);

        $response->assertInvalid(['phase_id' => 'This raid already has bosses, events or items, so it can only move to a phase with no game version.']);
    }

    #[Test]
    public function it_allows_moving_a_used_raid_within_its_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $raid = Raid::factory()->for(Phase::factory()->for($gameVersion))->withItems(1)->create();
        $samePhase = Phase::factory()->for($gameVersion)->create();
        $this->expectUpdate($raid, ['phase_id' => $samePhase->id]);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($raid))
            ->patch(route('management.raids.update', $raid), ['phase_id' => $samePhase->id]);

        $response->assertValid();
    }

    #[Test]
    public function it_allows_moving_an_unused_raid_to_any_phase(): void
    {
        $raid = Raid::factory()->for(Phase::factory()->for(GameVersion::factory()))->create();
        $otherPhase = Phase::factory()->for(GameVersion::factory())->create();
        $this->expectUpdate($raid, ['phase_id' => $otherPhase->id]);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($raid))
            ->patch(route('management.raids.update', $raid), ['phase_id' => $otherPhase->id]);

        $response->assertValid();
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_more_loot_councillors_than_the_stored_maximum_players(): void
    {
        $raid = Raid::factory()->create(['max_players' => 10]);
        UpdateRaid::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($raid))
            ->patch(route('management.raids.update', $raid), ['max_loot_councillors' => '11']);

        $response->assertInvalid(['max_loot_councillors' => "The maximum loot councillors can't be more than the maximum players."]);
    }

    // ==================== update — boss order ====================

    #[Group('happy-path')]
    #[Test]
    public function it_passes_the_boss_order(): void
    {
        $raid = Raid::factory()->create();
        [$first, $second] = Boss::factory()->for($raid)->count(2)->create();
        $this->expectUpdate($raid, ['boss_ids' => [$second->id, $first->id]]);

        $response = $this->actingAs($this->officer)
            ->from(route('management.raids.setup', [$raid, 'bosses']))
            ->withHeader('X-Autosave', '1')
            ->patchJson(route('management.raids.update', $raid), ['boss_ids' => [$second->id, $first->id]]);

        $response->assertValid();
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_boss_order_with_another_raids_boss(): void
    {
        $raid = Raid::factory()->create();
        $own = Boss::factory()->for($raid)->create();
        $foreign = Boss::factory()->for(Raid::factory())->create();
        UpdateRaid::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($raid))
            ->patchJson(route('management.raids.update', $raid), ['boss_ids' => [$own->id, $foreign->id]]);

        $response->assertInvalid(['boss_ids' => "Reorder this raid's own bosses."]);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_boss_order_that_leaves_out_a_boss(): void
    {
        $raid = Raid::factory()->create();
        [$first] = Boss::factory()->for($raid)->count(2)->create();
        UpdateRaid::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($raid))
            ->patchJson(route('management.raids.update', $raid), ['boss_ids' => [$first->id]]);

        $response->assertInvalid(['boss_ids' => "Reorder this raid's own bosses."]);
    }

    // ==================== update — banner ====================

    #[Group('happy-path')]
    #[Group('laravel-media-library')]
    #[Test]
    public function it_stores_a_banner_uploaded_as_a_spoofed_patch(): void
    {
        Storage::fake('public');
        $raid = Raid::factory()->create(['name' => 'Karazhan']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($raid))
            ->post(route('management.raids.update', $raid), [
                '_method' => 'PATCH',
                'banner' => UploadedFile::fake()->image('karazhan.webp'),
            ]);

        $response->assertRedirect($this->editUrl($raid));
        $this->assertSame('karazhan.webp', $raid->fresh()->getFirstMedia(Raid::MEDIA_BANNER)?->file_name);
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function it_flushes_the_raiding_cache_once_after_the_old_banner_is_replaced(): void
    {
        Storage::fake('public');
        $raid = EventFacade::fakeFor(
            fn () => Raid::factory()->withBanner()->create(['name' => 'Karazhan']),
            [MediaHasBeenAddedEvent::class],
        );
        Cache::tags(['raiding', 'events'])->put('canary', true, 60);
        $bannerCountsAtFlush = [];
        EventFacade::listen(CacheFlushed::class, function () use ($raid, &$bannerCountsAtFlush): void {
            $bannerCountsAtFlush[] = Media::where('model_id', $raid->id)->where('collection_name', Raid::MEDIA_BANNER)->count();
        });

        $this->actingAs($this->officer)
            ->from($this->editUrl($raid))
            ->post(route('management.raids.update', $raid), [
                '_method' => 'PATCH',
                'name' => 'Karazhan Remastered',
                'banner' => UploadedFile::fake()->image('karazhan.webp'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([1], $bannerCountsAtFlush);
        $this->assertFalse(Cache::tags(['raiding', 'events'])->has('canary'));
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function it_accepts_a_banner_position_with_a_banner_uploaded_in_the_same_request(): void
    {
        Storage::fake('public');
        $raid = Raid::factory()->create();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($raid))
            ->post(route('management.raids.update', $raid), [
                '_method' => 'PATCH',
                'banner' => UploadedFile::fake()->image('eye.webp'),
                'banner_position' => 'center bottom',
            ]);

        $response->assertValid();
        $this->assertSame('center bottom', $raid->fresh()->banner_position);
    }

    #[Test]
    public function it_passes_a_banner_position_for_a_stored_banner(): void
    {
        Storage::fake('public');
        $raid = Raid::factory()->withBanner()->create();
        $this->expectUpdate($raid, ['banner_position' => 'center top']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($raid))
            ->patch(route('management.raids.update', $raid), ['banner_position' => 'center top']);

        $response->assertValid();
    }

    #[Test]
    public function it_passes_a_banner_removed_as_null(): void
    {
        $raid = Raid::factory()->create();
        $this->expectUpdate($raid, ['banner' => null]);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($raid))
            ->patchJson(route('management.raids.update', $raid), ['banner' => null]);

        $response->assertValid();
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_banner_that_is_not_an_image(): void
    {
        $raid = Raid::factory()->create();
        UpdateRaid::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($raid))
            ->patch(route('management.raids.update', $raid), [
                'banner' => UploadedFile::fake()->create('banner.pdf', 10, 'application/pdf'),
            ]);

        $response->assertInvalid(['banner' => 'The banner must be a JPEG, PNG or WEBP image.']);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_banner_larger_than_2_mb(): void
    {
        $raid = Raid::factory()->create();
        UpdateRaid::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($raid))
            ->post(route('management.raids.update', $raid), [
                '_method' => 'PATCH',
                'banner' => UploadedFile::fake()->image('banner.webp')->size(2049),
            ]);

        $response->assertInvalid(['banner' => 'The banner must be 2 MB or smaller.']);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_banner_position_sent_with_the_banner_removed(): void
    {
        Storage::fake('public');
        $raid = Raid::factory()->withBanner()->create();
        UpdateRaid::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($raid))
            ->patchJson(route('management.raids.update', $raid), ['banner' => null, 'banner_position' => 'center top']);

        $response->assertInvalid(['banner_position' => 'Upload a banner before choosing its position.']);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_banner_position_without_a_banner(): void
    {
        $raid = Raid::factory()->create();
        UpdateRaid::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($raid))
            ->patch(route('management.raids.update', $raid), ['banner_position' => 'center top']);

        $response->assertInvalid(['banner_position' => 'Upload a banner before choosing its position.']);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_an_unknown_banner_position(): void
    {
        Storage::fake('public');
        $raid = Raid::factory()->withBanner()->create();
        UpdateRaid::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($raid))
            ->patch(route('management.raids.update', $raid), ['banner_position' => 'left']);

        $response->assertInvalid(['banner_position' => 'The selected banner position is invalid.']);
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_update_without_edit_datasets_permission(): void
    {
        $raid = Raid::factory()->create();
        $dashboardOnlyUser = User::factory()->withPermissions('view-officer-dashboard')->create();
        UpdateRaid::shouldNotRun();

        $response = $this->actingAs($dashboardOnlyUser)->patch(route('management.raids.update', $raid), ['name' => 'Nope']);

        $response->assertForbidden();
    }

    // ==================== destroy ====================

    #[Group('happy-path')]
    #[Test]
    public function it_deletes_a_raid_that_is_not_in_use(): void
    {
        $raid = Raid::factory()->create(['name' => 'Karazhan']);

        $response = $this->actingAs($this->officer)->delete(route('management.raids.destroy', $raid));

        $response->assertRedirect(route('management.raids.index'));
        $response->assertSessionHas('success', 'Deleted Karazhan.');
        $this->assertModelMissing($raid);
    }

    #[Test]
    public function it_refuses_to_delete_a_raid_with_bosses(): void
    {
        $raid = Raid::factory()->create(['name' => 'Karazhan']);
        Boss::factory()->for($raid)->create();

        $response = $this->actingAs($this->officer)
            ->from(route('management.raids.index'))
            ->delete(route('management.raids.destroy', $raid));

        $response->assertRedirect(route('management.raids.index'));
        $response->assertSessionHas('error', 'Karazhan is still linked to other records and cannot be deleted.');
        $this->assertModelExists($raid);
    }

    #[Test]
    public function it_refuses_to_delete_a_raid_in_an_upcoming_event(): void
    {
        $this->freezeTime();
        $raid = Raid::factory()->create(['name' => 'Karazhan']);
        $this->attachEvent($raid, now()->addDay(), now()->addDay()->addHours(3));

        $response = $this->actingAs($this->officer)
            ->from(route('management.raids.index'))
            ->delete(route('management.raids.destroy', $raid));

        $response->assertRedirect(route('management.raids.index'));
        $response->assertSessionHas('error', 'Karazhan is still linked to other records and cannot be deleted.');
        $this->assertModelExists($raid);
    }

    #[Test]
    public function it_refuses_to_delete_a_raid_in_an_event_that_is_in_progress(): void
    {
        $this->freezeTime();
        $raid = Raid::factory()->create(['name' => 'Karazhan']);
        $this->attachEvent($raid, now()->subHour(), now()->addHour());

        $response = $this->actingAs($this->officer)
            ->from(route('management.raids.index'))
            ->delete(route('management.raids.destroy', $raid));

        $response->assertRedirect(route('management.raids.index'));
        $response->assertSessionHas('error', 'Karazhan is still linked to other records and cannot be deleted.');
        $this->assertModelExists($raid);
    }

    #[Test]
    public function it_deletes_a_raid_with_only_past_events_and_removes_their_links(): void
    {
        $this->freezeTime();
        $raid = Raid::factory()->withItems(2)->create(['name' => 'Karazhan']);
        $event = $this->attachEvent($raid, now()->subDays(3), now()->subDays(3)->addHours(3));

        $response = $this->actingAs($this->officer)->delete(route('management.raids.destroy', $raid));

        $response->assertSessionHas('success', 'Deleted Karazhan.');
        $this->assertModelMissing($raid);
        $this->assertModelExists($event);
        $this->assertDatabaseMissing('pivot_events_raids', ['raid_id' => $raid->id]);
        $this->assertDatabaseMissing('pivot_items_raids', ['raid_id' => $raid->id]);
    }

    #[Test]
    public function it_deletes_a_raid_linked_to_an_event_template_and_removes_the_template_link(): void
    {
        $raid = Raid::factory()->create(['name' => 'Karazhan']);
        $template = Event::factory()->template()->create(['start_time' => null, 'end_time' => null]);
        $raid->events()->attach($template->id, ['sort_order' => 1]);

        $response = $this->actingAs($this->officer)->delete(route('management.raids.destroy', $raid));

        $response->assertSessionHas('success', 'Deleted Karazhan.');
        $this->assertModelMissing($raid);
        $this->assertModelExists($template);
        $this->assertDatabaseMissing('pivot_events_raids', ['event_id' => $template->id]);
    }

    #[Test]
    public function it_refuses_to_delete_a_raid_another_officer_is_editing(): void
    {
        $raid = Raid::factory()->create(['name' => 'Karazhan']);
        $raid->acquireEditLock(User::factory()->officer()->create());

        $response = $this->actingAs($this->officer)
            ->from(route('management.raids.index'))
            ->delete(route('management.raids.destroy', $raid));

        $response->assertRedirect(route('management.raids.index'));
        $response->assertSessionHas('error', "Someone is editing Karazhan, so it can't be deleted right now.");
        $this->assertModelExists($raid);
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_destroy_without_edit_datasets_permission(): void
    {
        $raid = Raid::factory()->create();
        $dashboardOnlyUser = User::factory()->withPermissions('view-officer-dashboard')->create();

        $response = $this->actingAs($dashboardOnlyUser)->delete(route('management.raids.destroy', $raid));

        $response->assertForbidden();
        $this->assertModelExists($raid);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function linkedRecords(): array
    {
        return [
            'a boss' => ['boss'],
            'an event' => ['event'],
            'an item' => ['item'],
        ];
    }

    // ==================== helpers ====================

    private function editUrl(Raid $raid): string
    {
        return route('management.raids.edit', $raid);
    }

    /**
     * Fake UpdateRaid and expect it to run once, for the given raid, with
     * exactly the given validated data.
     *
     * @param  array<string, mixed>  $data
     */
    private function expectUpdate(Raid $raid, array $data): void
    {
        UpdateRaid::shouldRun()
            ->once()
            ->with($this->isRaid($raid), $this->equalTo($data));
    }

    /**
     * Match the route-bound copy of the given raid in a faked action's arguments.
     */
    private function isRaid(Raid $raid): MatcherInterface
    {
        return Mockery::on(fn (Raid $given): bool => $given->is($raid));
    }

    private function attachEvent(Raid $raid, CarbonInterface $startTime, CarbonInterface $endTime): Event
    {
        $event = Event::factory()->create(['start_time' => $startTime, 'end_time' => $endTime]);
        $raid->events()->attach($event->id, ['sort_order' => 1]);

        return $event;
    }

    private function linkRecord(Raid $raid, string $link): void
    {
        match ($link) {
            'boss' => Boss::factory()->for($raid)->create(),
            'event' => $this->attachEvent($raid, now()->addDay(), now()->addDay()->addHours(3)),
            'item' => $raid->items()->attach(Item::factory()->create()),
        };
    }
}
