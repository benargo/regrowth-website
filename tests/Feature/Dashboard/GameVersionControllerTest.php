<?php

namespace Tests\Feature\Dashboard;

use App\Actions\GameVersion\BuildGameVersionRelationships;
use App\Actions\GameVersion\UpdateGameVersion;
use App\Enums\Faction;
use App\Enums\GameVersionSetupStep;
use App\Enums\Theme;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use App\Models\Raid;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Mockery\Matcher\MatcherInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Support\DashboardTestCase;

#[Group('platform')]
#[Group('raiding')]
class GameVersionControllerTest extends DashboardTestCase
{
    #[Test]
    public function it_redirects_guests_to_login(): void
    {
        $response = $this->get(route('management.game-versions.index'));

        $response->assertRedirect(route('login'));
    }

    // ==================== index ====================

    #[Group('authorization')]
    #[Test]
    public function it_forbids_members_from_the_index(): void
    {
        $member = User::factory()->member()->create();

        $response = $this->actingAs($member)->get(route('management.game-versions.index'));

        $response->assertForbidden();
    }

    #[Group('happy-path')]
    #[Test]
    public function it_lists_game_versions_by_release_date_with_usage_counts(): void
    {
        $later = GameVersion::factory()->create(['title' => 'Later', 'release_date' => Carbon::create(2026, 6, 1)]);
        GameVersion::factory()->create(['title' => 'Earlier', 'release_date' => Carbon::create(2025, 1, 1)]);
        $phase = Phase::factory()->for($later)->create();
        Raid::factory()->for($phase)->create();

        $response = $this->actingAs($this->officer)->get(route('management.game-versions.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/GameVersions/Index')
            ->has('gameVersions', 2)
            ->where('gameVersions.0.title', 'Earlier')
            ->where('gameVersions.0.phases_count', 0)
            ->where('gameVersions.1.title', 'Later')
            ->where('gameVersions.1.phases_count', 1)
            ->has('gameVersions.1.characters_count')
        );
    }

    // ==================== create ====================

    #[Group('happy-path')]
    #[Test]
    public function it_renders_the_create_page_with_enum_options(): void
    {
        $response = $this->actingAs($this->officer)->get(route('management.game-versions.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/GameVersions/Create')
            ->where('options.factions.0', ['value' => 'Alliance', 'label' => 'Alliance'])
            ->where('options.themes.0', ['value' => 'classic', 'label' => 'Classic'])
            ->where('options.blizzard_namespaces.0', ['value' => 'anniversary', 'label' => 'Anniversary'])
            ->where('steps', GameVersionSetupStep::options())
        );
    }

    // ==================== store ====================

    #[Group('happy-path')]
    #[Test]
    public function it_stores_a_game_version_and_redirects_to_the_first_setup_step(): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.game-versions.store'), $this->validPayload());

        $gameVersion = GameVersion::sole();
        $response->assertRedirect(route('management.game-versions.setup', [$gameVersion, 'races-and-classes']));
        $response->assertSessionHas('success', 'Added Wrath Classic.');

        $this->assertSame('Wrath Classic', $gameVersion->title);
        $this->assertSame('Gehennas', $gameVersion->realm);
        $this->assertSame(Faction::HORDE, $gameVersion->faction);
        $this->assertSame(Theme::FOREVER, $gameVersion->theme);
        $this->assertSame(BlizzardNamespace::CLASSIC, $gameVersion->blizzard_namespace);
        $this->assertSame(123456, $gameVersion->warcraftlogs_guild);
        $this->assertSame(1002, $gameVersion->warcraftlogs_expansion);
    }

    #[Test]
    public function it_stores_a_date_only_release_date_at_midnight_in_the_app_timezone(): void
    {
        $this->actingAs($this->officer)->post(route('management.game-versions.store'), $this->validPayload([
            'release_date' => '2026-02-06',
        ]));

        $this->assertTrue(
            GameVersion::sole()->release_date->equalTo(Carbon::create(2026, 2, 6, 0, 0, 0, 'Europe/Paris'))
        );
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_store_without_edit_datasets_permission(): void
    {
        $dashboardOnlyUser = User::factory()->withPermissions('view-officer-dashboard')->create();

        $response = $this->actingAs($dashboardOnlyUser)->post(route('management.game-versions.store'), $this->validPayload());

        $response->assertForbidden();
        $this->assertDatabaseCount('game_versions', 0);
    }

    #[Group('validation')]
    #[Test]
    public function it_requires_title_release_date_and_theme(): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.game-versions.store'), []);

        $response->assertInvalid([
            'title' => 'The game version title is required.',
            'release_date' => 'The release date is required.',
            'theme' => 'Please choose a theme.',
        ]);
        $this->assertDatabaseCount('game_versions', 0);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_values_outside_the_enums(): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.game-versions.store'), $this->validPayload([
            'faction' => 'Scourge',
            'theme' => 'neon',
            'blizzard_namespace' => 'forever',
        ]));

        $response->assertInvalid([
            'faction' => 'The selected faction is invalid.',
            'theme' => 'The selected theme is invalid.',
            'blizzard_namespace' => 'The selected Blizzard API namespace is invalid.',
        ]);
        $this->assertDatabaseCount('game_versions', 0);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_invalid_warcraftlogs_ids(): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.game-versions.store'), $this->validPayload([
            'warcraftlogs_guild' => 0,
            'warcraftlogs_expansion' => 'abc',
        ]));

        $response->assertInvalid([
            'warcraftlogs_guild' => 'The Warcraft Logs guild ID field must be at least 1.',
            'warcraftlogs_expansion' => 'The Warcraft Logs expansion ID field must be an integer.',
        ]);
        $this->assertDatabaseCount('game_versions', 0);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_duplicate_title_on_store(): void
    {
        GameVersion::factory()->create(['title' => 'Wrath Classic']);

        $response = $this->actingAs($this->officer)->post(route('management.game-versions.store'), $this->validPayload());

        $response->assertInvalid(['title' => 'A game version with this title already exists.']);
        $this->assertDatabaseCount('game_versions', 1);
    }

    // ==================== edit ====================

    #[Group('happy-path')]
    #[Test]
    public function it_renders_the_edit_page_with_the_game_version_and_options(): void
    {
        $gameVersion = GameVersion::factory()->create([
            'title' => 'Era',
            'release_date' => Carbon::create(2019, 8, 27, 0, 0, 0, 'Europe/Paris'),
            'faction' => Faction::HORDE,
        ]);
        $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)->get(route('management.game-versions.edit', $gameVersion));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/GameVersions/Edit')
            ->where('gameVersion.id', $gameVersion->id)
            ->where('gameVersion.title', 'Era')
            ->where('gameVersion.release_date', '2019-08-27')
            ->where('gameVersion.faction', 'Horde')
            ->has('options.themes', 2)
        );
    }

    #[Group('happy-path')]
    #[Test]
    public function it_shares_the_relationship_checklists_on_the_edit_page(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $relationships = $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)->get(route('management.game-versions.edit', $gameVersion));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('relationships', $relationships)
        );
    }

    #[Test]
    public function it_shares_the_setup_steps_on_the_edit_page(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)->get(route('management.game-versions.edit', $gameVersion));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('steps', GameVersionSetupStep::options())
        );
    }

    // ==================== edit lock ====================

    #[Group('happy-path')]
    #[Test]
    public function it_gives_the_first_officer_the_edit_lock(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)->get($this->editUrl($gameVersion));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('canEdit', true)
            ->where('editor', null)
        );
    }

    #[Test]
    public function it_shows_the_edit_page_read_only_to_a_second_officer_while_the_lock_is_held(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $holder = User::factory()->officer()->create(['nickname' => 'Holder']);
        $gameVersion->acquireEditLock($holder);
        $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)->get($this->editUrl($gameVersion));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('canEdit', false)
            ->where('editor', $holder->display_name)
        );
    }

    #[Test]
    public function it_shows_a_setup_step_read_only_to_a_second_officer_while_the_lock_is_held(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $holder = User::factory()->officer()->create();
        $gameVersion->acquireEditLock($holder);
        $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)
            ->get(route('management.game-versions.setup', [$gameVersion, GameVersionSetupStep::first()]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('canEdit', false)
            ->where('editor', $holder->display_name)
        );
    }

    #[Test]
    public function it_frees_the_edit_lock_once_the_holder_stops_refreshing_it(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $gameVersion->acquireEditLock(User::factory()->officer()->create());
        $this->fakeRelationships($gameVersion);

        $this->travel(GameVersion::EDIT_LOCK_SECONDS + 1)->seconds();
        $response = $this->actingAs($this->officer)->get($this->editUrl($gameVersion));

        $response->assertInertia(fn (Assert $page) => $page->where('canEdit', true));
    }

    #[Test]
    public function it_keeps_the_edit_lock_through_a_throttled_background_poll(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $gameVersion->acquireEditLock(User::factory()->officer()->create());
        $this->fakeRelationships($gameVersion);

        $this->travel(61)->seconds();
        $response = $this->actingAs($this->officer)->get($this->editUrl($gameVersion));

        $response->assertInertia(fn (Assert $page) => $page->where('canEdit', false));
    }

    #[Test]
    public function it_does_not_renew_the_edit_lock_on_an_idle_poll(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $gameVersion->acquireEditLock($this->officer);
        BuildGameVersionRelationships::shouldRun()->andReturn([]);

        $this->travel(GameVersion::EDIT_LOCK_SECONDS - 10)->seconds();
        $idlePoll = $this->actingAs($this->officer)
            ->withHeader('X-Edit-Idle', '1')
            ->get($this->editUrl($gameVersion));
        $this->travel(11)->seconds();
        $otherOfficer = $this->actingAs(User::factory()->officer()->create())
            ->withHeaders(['X-Edit-Idle' => null])
            ->get($this->editUrl($gameVersion));

        $idlePoll->assertInertia(fn (Assert $page) => $page->where('canEdit', true));
        $otherOfficer->assertInertia(fn (Assert $page) => $page->where('canEdit', true));
    }

    #[Test]
    public function it_does_not_take_a_free_edit_lock_on_an_idle_poll(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $this->actingAs($this->officer)
            ->withHeader('X-Edit-Idle', '1')
            ->get($this->editUrl($gameVersion));

        $this->assertFalse($gameVersion->isLockedForEditingBy(User::factory()->officer()->create()));
    }

    #[Test]
    public function it_shows_an_idle_officer_who_is_editing_once_someone_else_takes_the_lock(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $holder = User::factory()->officer()->create();
        $gameVersion->acquireEditLock($holder);
        $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)
            ->withHeader('X-Edit-Idle', '1')
            ->get($this->editUrl($gameVersion));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('canEdit', false)
            ->where('editor', $holder->display_name)
        );
    }

    #[Test]
    public function it_lets_the_holder_keep_the_edit_lock_across_visits(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $gameVersion->acquireEditLock($this->officer);
        $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)->get($this->editUrl($gameVersion));

        $response->assertInertia(fn (Assert $page) => $page->where('canEdit', true));
    }

    #[Test]
    public function it_rejects_an_update_while_another_officer_holds_the_edit_lock(): void
    {
        $gameVersion = GameVersion::factory()->create(['realm' => 'Gehennas']);
        $gameVersion->acquireEditLock(User::factory()->officer()->create());
        UpdateGameVersion::shouldRun()->never();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), ['realm' => 'Firemaw']);

        $response->assertRedirect($this->editUrl($gameVersion));
        $response->assertSessionHasErrors(['edit_lock' => 'Someone else is editing this game version. Your change was not saved.']);
    }

    #[Test]
    public function it_accepts_an_update_from_the_edit_lock_holder(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $gameVersion->acquireEditLock($this->officer);
        $this->expectUpdate($gameVersion, ['realm' => 'Firemaw']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), ['realm' => 'Firemaw']);

        $response->assertSessionHasNoErrors();
    }

    #[Test]
    public function it_refuses_to_delete_a_game_version_another_officer_is_editing(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Busy']);
        $gameVersion->acquireEditLock(User::factory()->officer()->create());

        $response = $this->actingAs($this->officer)
            ->from(route('management.game-versions.index'))
            ->delete(route('management.game-versions.destroy', $gameVersion));

        $response->assertRedirect(route('management.game-versions.index'));
        $response->assertSessionHas('error', "Someone is editing Busy, so it can't be deleted right now.");
        $this->assertModelExists($gameVersion);
    }

    // ==================== setup ====================

    #[Group('authorization')]
    #[Test]
    public function it_forbids_setup_without_edit_datasets_permission(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $dashboardOnlyUser = User::factory()->withPermissions('view-officer-dashboard')->create();
        BuildGameVersionRelationships::shouldNotRun();

        $response = $this->actingAs($dashboardOnlyUser)->get(route('management.game-versions.setup', [$gameVersion, 'phases']));

        $response->assertForbidden();
    }

    #[Group('happy-path')]
    #[Test]
    public function it_renders_a_setup_step_with_its_neighbours_and_relationship_options(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Era']);
        $relationships = $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)->get(route('management.game-versions.setup', [$gameVersion, 'phases']));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/GameVersions/Setup')
            ->where('gameVersion.title', 'Era')
            ->where('step', GameVersionSetupStep::PHASES->toOption())
            ->where('previousStep', GameVersionSetupStep::RACES_AND_CLASSES->toOption())
            ->where('nextStep', null)
            ->where('steps', GameVersionSetupStep::options())
            ->where('relationships', $relationships)
            ->where('returnToReview', false)
        );
    }

    #[Test]
    public function it_shares_return_to_review_on_a_setup_step_opened_from_the_review_page(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)->get(route('management.game-versions.setup', [$gameVersion, 'phases', 'review' => 1]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('returnToReview', true)
        );
    }

    #[Test]
    public function it_shares_no_next_step_on_the_last_setup_step(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)->get(route('management.game-versions.setup', [$gameVersion, 'phases']));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('nextStep', null)
        );
    }

    #[Test]
    #[TestWith(['zones'])]
    #[TestWith(['guild-tags'])]
    public function it_returns_not_found_for_an_unknown_setup_step(string $step): void
    {
        $gameVersion = GameVersion::factory()->create();

        $response = $this->actingAs($this->officer)->get(route('management.game-versions.setup', [$gameVersion, $step]));

        $response->assertNotFound();
    }

    // ==================== review ====================

    #[Group('authorization')]
    #[Test]
    public function it_forbids_review_without_edit_datasets_permission(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $dashboardOnlyUser = User::factory()->withPermissions('view-officer-dashboard')->create();
        BuildGameVersionRelationships::shouldNotRun();

        $response = $this->actingAs($dashboardOnlyUser)->get(route('management.game-versions.review', $gameVersion));

        $response->assertForbidden();
    }

    #[Group('happy-path')]
    #[Test]
    public function it_renders_the_review_page_with_the_details_steps_and_relationships(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Era']);
        $relationships = $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)->get(route('management.game-versions.review', $gameVersion));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/GameVersions/Review')
            ->where('gameVersion.title', 'Era')
            ->where('steps', GameVersionSetupStep::options())
            ->where('relationships', $relationships)
        );
    }

    // ==================== update ====================

    #[Group('happy-path')]
    #[Test]
    public function it_updates_a_game_version_and_redirects_back(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Era']);
        $this->expectUpdate($gameVersion, ['realm' => 'Firemaw']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), ['realm' => 'Firemaw']);

        $response->assertRedirect($this->editUrl($gameVersion));
        $response->assertSessionHas('success', 'Saved changes to Era.');
    }

    #[Test]
    public function it_saves_an_autosave_without_flashing_a_message(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->expectUpdate($gameVersion, ['realm' => 'Firemaw']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->withHeader('X-Autosave', '1')
            ->patch(route('management.game-versions.update', $gameVersion), ['realm' => 'Firemaw']);

        $response->assertRedirect($this->editUrl($gameVersion));
        $response->assertSessionMissing('success');
    }

    #[Test]
    public function it_allows_an_update_that_keeps_the_same_title(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Wrath Classic']);
        $this->expectUpdate($gameVersion, $this->validPayload());

        $response = $this->actingAs($this->officer)->patch(
            route('management.game-versions.update', $gameVersion),
            $this->validPayload()
        );

        $response->assertValid();
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_an_update_to_another_game_versions_title(): void
    {
        GameVersion::factory()->create(['title' => 'Taken']);
        $gameVersion = GameVersion::factory()->create(['title' => 'Mine']);
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)->patch(
            route('management.game-versions.update', $gameVersion),
            $this->validPayload(['title' => 'Taken'])
        );

        $response->assertInvalid(['title' => 'A game version with this title already exists.']);
    }

    #[Test]
    public function it_passes_optional_fields_submitted_blank_as_null(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->expectUpdate($gameVersion, $this->validPayload([
            'title' => $gameVersion->title,
            'realm' => null,
            'faction' => null,
            'blizzard_namespace' => null,
            'warcraftlogs_guild' => null,
            'warcraftlogs_expansion' => null,
        ]));

        $response = $this->actingAs($this->officer)->patch(route('management.game-versions.update', $gameVersion), $this->validPayload([
            'title' => $gameVersion->title,
            'realm' => '',
            'faction' => '',
            'blizzard_namespace' => '',
            'warcraftlogs_guild' => '',
            'warcraftlogs_expansion' => '',
        ]));

        $response->assertValid();
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_update_without_edit_datasets_permission(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $dashboardOnlyUser = User::factory()->withPermissions('view-officer-dashboard')->create();
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($dashboardOnlyUser)
            ->patch(route('management.game-versions.update', $gameVersion), ['title' => 'Theirs']);

        $response->assertForbidden();
    }

    #[Test]
    public function it_accepts_an_update_that_sends_only_some_fields(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->expectUpdate($gameVersion, ['realm' => 'Firemaw']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), ['realm' => 'Firemaw']);

        $response->assertValid();
    }

    #[Test]
    public function it_accepts_optional_fields_sent_as_null(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $nulls = ['faction' => null, 'theme' => null, 'warcraftlogs_guild' => null];
        $this->expectUpdate($gameVersion, $nulls);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patchJson(route('management.game-versions.update', $gameVersion), $nulls);

        $response->assertValid();
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_title_or_release_date_sent_as_null(): void
    {
        $gameVersion = GameVersion::factory()->create();
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patchJson(route('management.game-versions.update', $gameVersion), [
                'title' => null,
                'release_date' => null,
            ]);

        $response->assertInvalid([
            'title' => 'The game version title is required.',
            'release_date' => 'The release date is required.',
        ]);
    }

    // ==================== update: races and classes ====================

    #[Group('happy-path')]
    #[Test]
    public function it_saves_the_ticked_races_and_classes(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $race = PlayableRace::factory()->create();
        $class = PlayableClass::factory()->create();
        $payload = ['playable_race_ids' => [$race->id], 'playable_class_ids' => [$class->id]];
        $this->expectUpdate($gameVersion, $payload);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), $payload);

        $response->assertRedirect($this->editUrl($gameVersion));
        $response->assertSessionHas('success', "Saved changes to {$gameVersion->title}.");
    }

    #[Test]
    public function it_accepts_empty_race_and_class_lists(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $payload = ['playable_race_ids' => [], 'playable_class_ids' => []];
        $this->expectUpdate($gameVersion, $payload);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patchJson(route('management.game-versions.update', $gameVersion), $payload);

        $response->assertValid();
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_race_that_does_not_exist(): void
    {
        $gameVersion = GameVersion::factory()->create();
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), [
                'playable_race_ids' => [999999],
            ]);

        $response->assertInvalid([
            'playable_race_ids.0' => 'One of the selected races no longer exists. Reload the page and try again.',
        ]);
    }

    // ==================== update: phases ====================

    #[Group('happy-path')]
    #[Test]
    public function it_saves_the_ticked_phases(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phase = Phase::factory()->create();
        $this->expectUpdate($gameVersion, ['phase_ids' => [$phase->id]]);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), ['phase_ids' => [$phase->id]]);

        $response->assertRedirect($this->editUrl($gameVersion));
    }

    #[Test]
    public function it_accepts_an_empty_phase_list(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->expectUpdate($gameVersion, ['phase_ids' => []]);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patchJson(route('management.game-versions.update', $gameVersion), ['phase_ids' => []]);

        $response->assertValid();
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_phase_list_sent_as_null(): void
    {
        $gameVersion = GameVersion::factory()->create();
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patchJson(route('management.game-versions.update', $gameVersion), ['phase_ids' => null]);

        $response->assertInvalid(['phase_ids' => 'The phase ids field must be an array.']);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_phase_that_does_not_exist(): void
    {
        $gameVersion = GameVersion::factory()->create();
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), ['phase_ids' => [999999]]);

        $response->assertInvalid([
            'phase_ids.0' => 'One of the selected phases no longer exists. Reload the page and try again.',
        ]);
    }

    // ==================== update: new phase ====================

    #[Group('happy-path')]
    #[Test]
    public function it_adds_a_new_phase(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $payload = [
            'new_phase' => [
                'number' => '2.5',
                'description' => "Zul'Aman",
                'start_date' => '2026-05-12',
            ],
        ];
        $this->expectUpdate($gameVersion, $payload);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), $payload);

        $response->assertRedirect($this->editUrl($gameVersion));
        $response->assertSessionHas('success', "Saved changes to {$gameVersion->title}.");
    }

    #[Group('validation')]
    #[Test]
    public function it_requires_a_new_phase_number_and_description(): void
    {
        $gameVersion = GameVersion::factory()->create();
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), [
                'new_phase' => ['number' => '', 'description' => '', 'start_date' => ''],
            ]);

        $response->assertInvalid([
            'new_phase.number' => 'Enter the phase number.',
            'new_phase.description' => 'Enter a description for the phase.',
        ]);
    }

    #[Group('validation')]
    #[TestWith(['10', 'The phase number field must be between 0 and 9.9.'])]
    #[TestWith(['-1', 'The phase number field must be between 0 and 9.9.'])]
    #[TestWith(['1.25', 'The phase number field must have 0-1 decimal places.'])]
    #[Test]
    public function it_rejects_a_phase_number_the_column_cannot_hold(string $number, string $message): void
    {
        $gameVersion = GameVersion::factory()->create();
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), [
                'new_phase' => ['number' => $number, 'description' => 'Too far'],
            ]);

        $response->assertInvalid(['new_phase.number' => $message]);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_phase_number_the_game_version_already_uses(): void
    {
        $gameVersion = GameVersion::factory()->create();
        Phase::factory()->for($gameVersion)->create(['number' => '2.0']);
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), [
                'new_phase' => ['number' => '2', 'description' => 'Second'],
            ]);

        $response->assertInvalid(['new_phase.number' => 'This game version already has a phase with that number.']);
    }

    #[Test]
    public function it_allows_a_phase_number_another_game_version_uses(): void
    {
        $gameVersion = GameVersion::factory()->create();
        Phase::factory()->for(GameVersion::factory())->create(['number' => '2.0']);
        $this->expectUpdate($gameVersion, ['new_phase' => ['number' => '2.0', 'description' => 'Second']]);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), [
                'new_phase' => ['number' => '2.0', 'description' => 'Second'],
            ]);

        $response->assertValid();
    }

    // ==================== update: raids are read-only ====================

    #[Group('validation')]
    #[Test]
    public function it_ignores_a_raid_ids_field_since_raids_are_not_directly_assignable(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $raid = Raid::factory()->create();
        // expectUpdate() asserts the *validated* payload passed to UpdateGameVersion::run().
        // raid_ids is no longer a recognized rule, so FormRequest::validated() strips it
        // and the action receives an empty array, not the raid_ids the client sent.
        $this->expectUpdate($gameVersion, []);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), ['raid_ids' => [$raid->id]]);

        $response->assertRedirect($this->editUrl($gameVersion));
        $response->assertValid();
    }

    // ==================== update: new raid ====================

    #[Group('happy-path')]
    #[Test]
    public function it_adds_a_new_raid_under_one_of_the_game_versions_phases(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phase = Phase::factory()->for($gameVersion)->create();
        $payload = [
            'new_raid' => [
                'name' => 'Karazhan',
                'difficulty' => 'Normal',
                'phase_id' => $phase->id,
                'max_players' => 10,
            ],
        ];
        $this->expectUpdate($gameVersion, $payload);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), $payload);

        $response->assertRedirect($this->editUrl($gameVersion));
    }

    #[Group('validation')]
    #[Test]
    public function it_requires_a_new_raid_name_difficulty_and_phase(): void
    {
        $gameVersion = GameVersion::factory()->create();
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), [
                'new_raid' => ['name' => '', 'difficulty' => '', 'phase_id' => '', 'max_players' => ''],
            ]);

        $response->assertInvalid([
            'new_raid.name' => 'Enter the raid name.',
            'new_raid.difficulty' => 'Enter the raid difficulty, for example Normal.',
            'new_raid.phase_id' => 'Choose which phase the raid belongs to.',
        ]);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_new_raid_under_a_phase_from_another_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $foreignPhase = Phase::factory()->for(GameVersion::factory())->create();
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), [
                'new_raid' => ['name' => 'Karazhan', 'difficulty' => 'Normal', 'phase_id' => $foreignPhase->id],
            ]);

        $response->assertInvalid(['new_raid.phase_id' => "Choose one of this game version's phases."]);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_maximum_player_count_above_forty(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phase = Phase::factory()->for($gameVersion)->create();
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), [
                'new_raid' => ['name' => 'Karazhan', 'difficulty' => 'Normal', 'phase_id' => $phase->id, 'max_players' => 41],
            ]);

        $response->assertInvalid(['new_raid.max_players' => 'The maximum players field must be between 1 and 40.']);
    }

    // ==================== update: removed fields ====================

    #[Test]
    public function it_ignores_guild_tag_ids_sent_by_a_stale_page(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->expectUpdate($gameVersion, ['realm' => 'Firemaw']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), [
                'realm' => 'Firemaw',
                'guild_tag_ids' => [999999],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect($this->editUrl($gameVersion));
    }

    // ==================== destroy ====================

    #[Group('happy-path')]
    #[Test]
    public function it_deletes_a_game_version_that_is_not_in_use(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Unused']);

        $response = $this->actingAs($this->officer)->delete(route('management.game-versions.destroy', $gameVersion));

        $response->assertRedirect(route('management.game-versions.index'));
        $response->assertSessionHas('success', 'Deleted Unused.');
        $this->assertModelMissing($gameVersion);
    }

    #[Test]
    public function it_refuses_to_delete_a_game_version_that_is_in_use(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Live']);
        $phase = Phase::factory()->for($gameVersion)->create();
        Raid::factory()->for($phase)->create();

        $response = $this->actingAs($this->officer)
            ->from(route('management.game-versions.index'))
            ->delete(route('management.game-versions.destroy', $gameVersion));

        $response->assertRedirect(route('management.game-versions.index'));
        $response->assertSessionHas('error', 'Live is still linked to other records and cannot be deleted.');
        $this->assertModelExists($gameVersion);
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_destroy_without_edit_datasets_permission(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $dashboardOnlyUser = User::factory()->withPermissions('view-officer-dashboard')->create();

        $response = $this->actingAs($dashboardOnlyUser)->delete(route('management.game-versions.destroy', $gameVersion));

        $response->assertForbidden();
        $this->assertModelExists($gameVersion);
    }

    // ==================== helpers ====================

    /**
     * Build a valid create/update payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'title' => 'Wrath Classic',
            'realm' => 'Gehennas',
            'faction' => Faction::HORDE->value,
            'release_date' => '2022-09-26',
            'theme' => Theme::FOREVER->value,
            'blizzard_namespace' => BlizzardNamespace::CLASSIC->value,
            'warcraftlogs_guild' => 123456,
            'warcraftlogs_expansion' => 1002,
            ...$overrides,
        ];
    }

    private function editUrl(GameVersion $gameVersion): string
    {
        return route('management.game-versions.edit', $gameVersion);
    }

    /**
     * Fake UpdateGameVersion and expect it to run once, for the given game
     * version, with exactly the given validated data. equalTo() ignores key
     * order but not key presence, so an absent key and a null key differ.
     *
     * @param  array<string, mixed>  $data
     */
    private function expectUpdate(GameVersion $gameVersion, array $data): void
    {
        UpdateGameVersion::shouldRun()
            ->once()
            ->with($this->isGameVersion($gameVersion), $this->equalTo($data));
    }

    /**
     * Match the route-bound copy of the given game version in a faked action's
     * arguments. Route binding loads a fresh instance, so identity won't do.
     */
    private function isGameVersion(GameVersion $gameVersion): MatcherInterface
    {
        return Mockery::on(fn (GameVersion $given): bool => $given->is($gameVersion));
    }

    /**
     * Fake BuildGameVersionRelationships for the given game version and return
     * the stub checklist data it hands back, for the test to find in the props.
     *
     * @return array<string, array{options: list<array<string, mixed>>, selected_ids: list<int>}>
     */
    private function fakeRelationships(GameVersion $gameVersion): array
    {
        $relationships = ['phases' => ['options' => [], 'selected_ids' => [7]]];

        BuildGameVersionRelationships::shouldRun()
            ->once()
            ->with($this->isGameVersion($gameVersion))
            ->andReturn($relationships);

        return $relationships;
    }
}
