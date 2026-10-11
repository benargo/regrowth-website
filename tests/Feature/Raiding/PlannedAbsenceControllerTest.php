<?php

namespace Tests\Feature\Raiding;

use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\GameVersion;
use App\Models\Permission;
use App\Models\PlannedAbsence;
use App\Models\User;
use App\Services\Discord\Discord;
use App\Services\Discord\Resources\GuildMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

#[Group('raiding')]
class PlannedAbsenceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $officerRole = DiscordRole::firstOrCreate(
            ['id' => '829021769448816691'],
            ['name' => 'Officer', 'position' => 6, 'is_visible' => true]
        );
        $memberRole = DiscordRole::firstOrCreate(
            ['id' => '829022020301094922'],
            ['name' => 'Member', 'position' => 3, 'is_visible' => true]
        );

        $viewPermission = Permission::firstOrCreate(['name' => 'view-planned-absences', 'guard_name' => 'web']);
        $createPermission = Permission::firstOrCreate(['name' => 'create-planned-absences', 'guard_name' => 'web']);
        $createForOthersPermission = Permission::firstOrCreate(['name' => 'manage-planned-absences', 'guard_name' => 'web']);
        $updatePermission = Permission::firstOrCreate(['name' => 'update-planned-absences', 'guard_name' => 'web']);
        $deletePermission = Permission::firstOrCreate(['name' => 'delete-planned-absences', 'guard_name' => 'web']);

        $officerRole->givePermissionTo($viewPermission);
        $officerRole->givePermissionTo($createPermission);
        $officerRole->givePermissionTo($createForOthersPermission);
        $officerRole->givePermissionTo($updatePermission);
        $officerRole->givePermissionTo($deletePermission);
        $memberRole->givePermissionTo($viewPermission);
    }

    // ==================== index: Access Control ====================

    #[Test]
    public function index_requires_authentication(): void
    {
        $response = $this->get(route('raiding.absences.index'));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function index_allows_authenticated_user(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('raiding.absences.index'));

        $response->assertOk();
    }

    // ==================== index: Inertia Response ====================

    #[Test]
    public function index_renders_correct_inertia_component(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('raiding.absences.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/Absences/Index')
        );
    }

    // ==================== index: Deferred Props ====================

    #[Test]
    public function index_returns_empty_collection_when_no_absences_exist(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('raiding.absences.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/Absences/Index')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('planned_absences', 0)
            )
        );
    }

    #[Test]
    public function index_deferred_planned_absences_returns_collection_with_expected_structure(): void
    {
        $user = User::factory()->member()->create();
        PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($user)->get(route('raiding.absences.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/Absences/Index')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('planned_absences', 1)
                ->has('planned_absences.0', fn (Assert $absence) => $absence
                    ->has('id')
                    ->has('character')
                    ->has('start_date')
                    ->has('end_date')
                    ->has('reason')
                    ->has('discord_message_id')
                    ->has('created_by')
                    ->has('created_at')
                    ->has('updated_at')
                    ->has('deleted_at')
                )
            )
        );
    }

    #[Test]
    public function index_orders_absences_by_start_date(): void
    {
        $user = User::factory()->member()->create();

        $later = PlannedAbsence::factory()->withCharacter()->create([
            'start_date' => now()->addDays(5),
        ]);
        $sooner = PlannedAbsence::factory()->withCharacter()->create([
            'start_date' => now()->addDay(),
        ]);

        $response = $this->actingAs($user)->get(route('raiding.absences.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('planned_absences', 2)
                ->where('planned_absences.0.id', $sooner->id)
                ->where('planned_absences.1.id', $later->id)
            )
        );
    }

    // ==================== create: Access Control ====================

    #[Test]
    public function create_requires_authentication(): void
    {
        $response = $this->get(route('raiding.absences.create'));

        $response->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function create_returns_403_without_create_for_others_permission(): void
    {
        $user = $this->member();

        $response = $this->actingAs($user)->get(route('raiding.absences.create'));

        $response->assertForbidden();
    }

    #[Test]
    public function create_allows_user_with_create_permission(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.absences.create'));

        $response->assertOk();
    }

    // ==================== create: Inertia Response ====================

    #[Test]
    public function create_renders_correct_inertia_component(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.absences.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/Absences/Form')
        );
    }

    #[Test]
    #[Group('happy-path')]
    public function create_lists_only_mains_in_the_current_version(): void
    {
        [$current, $other] = $this->twoReleasedVersions();
        $main = Character::factory()->main()->forGameVersion($current)->create(['name' => 'Thrall']);
        Character::factory()->forGameVersion($current)->create(['name' => 'Jaina', 'is_main' => false]);
        Character::factory()->main()->forGameVersion($other)->create(['name' => 'Sylvanas']);
        Character::factory()->main()->create(['name' => 'Versionless', 'game_version_id' => null]);

        $this->actingAs($this->officer())
            ->withSession([ResolveCurrentGameVersion::SESSION_KEY => $current->slug])
            ->get(route('raiding.absences.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Raiding/Absences/Form')
                ->where('game_version.id', $current->id)
                ->has('game_versions', 2)
                ->where('characters', fn ($characters) => collect($characters)->pluck('id')->all() === [$main->id]));
    }

    #[Test]
    public function create_uses_the_session_version_when_it_is_selectable(): void
    {
        [, $older] = $this->twoReleasedVersions();
        $main = Character::factory()->main()->forGameVersion($older)->create(['name' => 'Sylvanas']);

        $this->actingAs($this->officer())
            ->withSession([ResolveCurrentGameVersion::SESSION_KEY => $older->slug])
            ->get(route('raiding.absences.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('game_version.id', $older->id)
                ->where('characters', fn ($characters) => collect($characters)->pluck('id')->all() === [$main->id]));
    }

    #[Test]
    public function create_falls_back_to_the_newest_released_version(): void
    {
        [$newer] = $this->twoReleasedVersions();

        $this->actingAs($this->officer())
            ->get(route('raiding.absences.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('game_version.id', $newer->id)
                ->where('game_versions.0.id', $newer->id));
    }

    #[Test]
    public function create_does_not_offer_unreleased_versions(): void
    {
        [$newer, $older] = $this->twoReleasedVersions();
        $unreleased = GameVersion::factory()->create(['release_date' => now()->addMonth()]);

        $this->actingAs($this->officer())
            ->withSession([ResolveCurrentGameVersion::SESSION_KEY => $unreleased->slug])
            ->get(route('raiding.absences.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('game_version.id', $newer->id)
                ->where('game_versions', fn ($gameVersions) => collect($gameVersions)->pluck('id')->all() === [$newer->id, $older->id]));
    }

    #[Test]
    public function create_does_not_share_the_picker_cache_between_versions(): void
    {
        [$newer, $older] = $this->twoReleasedVersions();
        Character::factory()->main()->forGameVersion($newer)->create(['name' => 'Thrall']);
        $olderMain = Character::factory()->main()->forGameVersion($older)->create(['name' => 'Sylvanas']);
        $officer = $this->officer();

        $this->actingAs($officer)
            ->withSession([ResolveCurrentGameVersion::SESSION_KEY => $newer->slug])
            ->get(route('raiding.absences.create'))
            ->assertOk();

        $this->actingAs($officer)
            ->withSession([ResolveCurrentGameVersion::SESSION_KEY => $older->slug])
            ->get(route('raiding.absences.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('characters', fn ($characters) => collect($characters)->pluck('id')->all() === [$olderMain->id]));
    }

    #[Test]
    public function create_lists_a_main_promoted_after_the_picker_was_cached(): void
    {
        [$current] = $this->twoReleasedVersions();
        $character = Character::factory()->forGameVersion($current)->create(['name' => 'Thrall', 'is_main' => false]);
        $officer = $this->officer();

        $this->actingAs($officer)
            ->withSession([ResolveCurrentGameVersion::SESSION_KEY => $current->slug])
            ->get(route('raiding.absences.create'))
            ->assertInertia(fn (Assert $page) => $page->where('characters', []));

        $character->update(['is_main' => true]);

        $this->actingAs($officer)
            ->withSession([ResolveCurrentGameVersion::SESSION_KEY => $current->slug])
            ->get(route('raiding.absences.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('characters', fn ($characters) => collect($characters)->pluck('id')->all() === [$character->id]));
    }

    #[Test]
    public function create_remembers_the_requested_game_version(): void
    {
        [, $older] = $this->twoReleasedVersions();

        $this->actingAs($this->officer())
            ->get(route('raiding.absences.create', ['game_version' => $older->slug]))
            ->assertRedirect(route('raiding.absences.create'))
            ->assertSessionHas(ResolveCurrentGameVersion::SESSION_KEY, $older->slug);
    }

    // ==================== create: action prop ====================

    #[Test]
    public function create_passes_action_url_to_page(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.absences.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('action', route('raiding.absences.store'))
        );
    }

    // ==================== edit: Access Control ====================

    #[Test]
    public function edit_requires_authentication(): void
    {
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->get(route('raiding.absences.edit', $absence));

        $response->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function edit_requires_authorization(): void
    {
        $member = $this->member();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['created_by' => $member->id]);

        $response = $this->actingAs($member)->get(route('raiding.absences.edit', $absence));

        $response->assertForbidden();
    }

    #[Test]
    public function edit_allows_user_with_update_permission(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($user)->get(route('raiding.absences.edit', $absence));

        $response->assertOk();
    }

    // ==================== edit: Inertia Response ====================

    #[Test]
    public function edit_renders_correct_inertia_component(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($user)->get(route('raiding.absences.edit', $absence));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/Absences/Form')
        );
    }

    #[Test]
    public function edit_passes_planned_absence_to_page(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create([
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-07',
            'reason' => 'On holiday.',
        ]);

        $response = $this->actingAs($user)->get(route('raiding.absences.edit', $absence));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/Absences/Form')
            ->has('planned_absence', fn (Assert $data) => $data
                ->where('id', $absence->id)
                ->has('character')
                ->where('start_date', '2026-06-01')
                ->where('end_date', '2026-06-07')
                ->where('reason', 'On holiday.')
                ->has('discord_message_id')
                ->has('created_by')
                ->has('created_at')
                ->has('updated_at')
                ->has('deleted_at')
            )
        );
    }

    #[Test]
    public function edit_passes_action_url_to_page(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($user)->get(route('raiding.absences.edit', $absence));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('action', route('raiding.absences.update', $absence))
        );
    }

    #[Test]
    public function edit_uses_the_characters_game_version(): void
    {
        [$newer, $older] = $this->twoReleasedVersions();
        $character = Character::factory()->main()->forGameVersion($older)->create(['name' => 'Sylvanas']);
        Character::factory()->main()->forGameVersion($newer)->create(['name' => 'Thrall']);
        $absence = PlannedAbsence::factory()->create(['character_id' => $character->id]);

        $this->actingAs($this->officer())
            ->withSession([ResolveCurrentGameVersion::SESSION_KEY => $newer->slug])
            ->get(route('raiding.absences.edit', $absence))
            ->assertInertia(fn (Assert $page) => $page
                ->where('game_version.id', $older->id)
                ->missing('game_versions')
                ->where('characters', fn ($characters) => collect($characters)->pluck('id')->all() === [$character->id]));
    }

    #[Test]
    public function edit_falls_back_to_the_current_version_for_a_versionless_character(): void
    {
        [$newer, $older] = $this->twoReleasedVersions();
        $versionless = Character::factory()->main()->create(['game_version_id' => null]);
        $absence = PlannedAbsence::factory()->create(['character_id' => $versionless->id]);

        $this->actingAs($this->officer())
            ->withSession([ResolveCurrentGameVersion::SESSION_KEY => $older->slug])
            ->get(route('raiding.absences.edit', $absence))
            ->assertInertia(fn (Assert $page) => $page->where('game_version.id', $older->id));
    }

    // ==================== store: Access Control ====================

    #[Group('authorization')]
    #[Test]
    public function store_requires_authentication(): void
    {
        $response = $this->postJson(route('raiding.absences.store'));

        $response->assertUnauthorized();
    }

    #[Group('authorization')]
    #[Test]
    public function store_returns_403_without_create_for_others_permission(): void
    {
        $user = $this->member();
        $character = $this->mainInReleasedVersion();

        $response = $this->actingAs($user)->postJson(route('raiding.absences.store'), [
            'game_version' => $character->game_version_id,
            'game_version' => $character->game_version_id,
            'character' => $character->id,
            'start_date' => now()->addDay()->format('Y-m-d'),
            'reason' => 'Going on holiday.',
        ]);

        $response->assertForbidden();
    }

    // ==================== store: Happy Paths ====================

    #[Test]
    public function store_creates_absence_with_character_id(): void
    {
        $user = User::factory()->officer()->create();
        $character = $this->mainInReleasedVersion();

        $startDate = now()->addDay();
        $endDate = now()->addDays(7);

        $response = $this->actingAs($user)->post(route('raiding.absences.store'), [
            'game_version' => $character->game_version_id,
            'character' => $character->id,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'reason' => 'Going on holiday.',
        ]);

        $response->assertRedirectToRoute('raiding.absences.index');
        $this->assertDatabaseHas('planned_absences', [
            'character_id' => $character->id,
            'start_date' => $startDate->format('Y-m-d').' 00:00:00',
            'end_date' => $endDate->format('Y-m-d').' 00:00:00',
            'created_by' => $user->id,
        ]);
    }

    #[Test]
    public function store_creates_absence_without_end_date(): void
    {
        $user = User::factory()->officer()->create();
        $character = $this->mainInReleasedVersion();

        $response = $this->actingAs($user)->post(route('raiding.absences.store'), [
            'game_version' => $character->game_version_id,
            'character' => $character->id,
            'start_date' => now()->addDay()->format('Y-m-d'),
            'reason' => 'Indefinite absence.',
        ]);

        $response->assertRedirectToRoute('raiding.absences.index');
        $this->assertDatabaseHas('planned_absences', [
            'character_id' => $character->id,
            'end_date' => null,
        ]);
    }

    // ==================== store: Validation ====================

    #[Group('validation')]
    #[Test]
    public function store_requires_character(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->postJson(route('raiding.absences.store'), [
            'start_date' => now()->addDay()->format('Y-m-d'),
            'reason' => 'Away for a week.',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['character']);
    }

    #[Group('validation')]
    #[Test]
    public function store_requires_start_date(): void
    {
        $user = User::factory()->officer()->create();
        $character = $this->mainInReleasedVersion();

        $response = $this->actingAs($user)->postJson(route('raiding.absences.store'), [
            'game_version' => $character->game_version_id,
            'character' => $character->id,
            'reason' => 'Away for a week.',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['start_date']);
    }

    #[Group('validation')]
    #[Test]
    public function store_requires_reason(): void
    {
        $user = User::factory()->officer()->create();
        $character = $this->mainInReleasedVersion();

        $response = $this->actingAs($user)->postJson(route('raiding.absences.store'), [
            'game_version' => $character->game_version_id,
            'character' => $character->id,
            'start_date' => now()->addDay()->format('Y-m-d'),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['reason']);
    }

    #[Group('validation')]
    #[Test]
    public function store_validates_end_date_must_be_after_start_date(): void
    {
        $user = User::factory()->officer()->create();
        $character = $this->mainInReleasedVersion();

        $response = $this->actingAs($user)->postJson(route('raiding.absences.store'), [
            'game_version' => $character->game_version_id,
            'character' => $character->id,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->addDay()->format('Y-m-d'),
            'reason' => 'Away for a week.',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['end_date']);
    }

    #[Group('validation')]
    #[Test]
    public function store_rejects_an_alt(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $alt = Character::factory()->forGameVersion($gameVersion)->create(['is_main' => false]);

        $this->actingAs($this->officer())
            ->post(route('raiding.absences.store'), $this->absencePayload($gameVersion, $alt))
            ->assertSessionHasErrors(['character' => 'Choose a main character.']);

        $this->assertDatabaseCount('planned_absences', 0);
    }

    #[Group('validation')]
    #[Test]
    public function store_rejects_a_character_from_another_version(): void
    {
        [$newer, $older] = $this->twoReleasedVersions();
        $main = Character::factory()->main()->forGameVersion($older)->create();

        $this->actingAs($this->officer())
            ->post(route('raiding.absences.store'), $this->absencePayload($newer, $main))
            ->assertSessionHasErrors(['character' => 'Choose a main character.']);

        $this->assertDatabaseCount('planned_absences', 0);
    }

    #[Group('validation')]
    #[Test]
    public function store_rejects_a_missing_game_version_even_for_a_versionless_character(): void
    {
        $versionless = Character::factory()->main()->create(['game_version_id' => null]);

        $this->actingAs($this->officer())
            ->post(route('raiding.absences.store'), [
                'character' => $versionless->id,
                'start_date' => now()->addDay()->format('Y-m-d'),
                'reason' => 'Away.',
            ])
            ->assertSessionHasErrors(['game_version' => 'A game version is required.', 'character' => 'Choose a main character.']);

        $this->assertDatabaseCount('planned_absences', 0);
    }

    #[Group('validation')]
    #[Test]
    public function store_rejects_an_unreleased_game_version(): void
    {
        $unreleased = GameVersion::factory()->create(['release_date' => now()->addMonth()]);
        $main = Character::factory()->main()->forGameVersion($unreleased)->create();

        $this->actingAs($this->officer())
            ->post(route('raiding.absences.store'), $this->absencePayload($unreleased, $main))
            ->assertSessionHasErrors(['game_version' => "That game version isn't available."]);

        $this->assertDatabaseCount('planned_absences', 0);
    }

    // ==================== update: Access Control ====================

    #[Group('authorization')]
    #[Test]
    public function update_requires_authentication(): void
    {
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->patchJson(route('raiding.absences.update', $absence));

        $response->assertUnauthorized();
    }

    #[Group('authorization')]
    #[Test]
    public function update_requires_authorization(): void
    {
        $member = $this->member();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['created_by' => $member->id]);

        $response = $this->actingAs($member)->patchJson(route('raiding.absences.update', $absence), [
            'reason' => 'Updated reason.',
        ]);

        $response->assertForbidden();
    }

    // ==================== update: Happy Paths ====================

    #[Test]
    public function update_updates_start_date(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['start_date' => now()->addDay()]);

        $newStartDate = now()->addDays(7);

        $response = $this->actingAs($user)->patchJson(route('raiding.absences.update', $absence), [
            'start_date' => $newStartDate->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('planned_absences', [
            'id' => $absence->id,
            'start_date' => $newStartDate->format('Y-m-d').' 00:00:00',
        ]);
    }

    #[Test]
    public function update_updates_end_date(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create([
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(7),
        ]);

        $newEndDate = now()->addDays(14);

        $response = $this->actingAs($user)->patchJson(route('raiding.absences.update', $absence), [
            'end_date' => $newEndDate->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('planned_absences', [
            'id' => $absence->id,
            'end_date' => $newEndDate->format('Y-m-d').' 00:00:00',
        ]);
    }

    #[Test]
    public function update_updates_reason(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['reason' => 'Old reason.']);

        $response = $this->actingAs($user)->patchJson(route('raiding.absences.update', $absence), [
            'reason' => 'New reason.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('planned_absences', [
            'id' => $absence->id,
            'reason' => 'New reason.',
        ]);
    }

    #[Test]
    public function update_updates_character_with_permission(): void
    {
        $user = User::factory()->officer()->create();
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $originalCharacter = Character::factory()->main()->forGameVersion($gameVersion)->create(['name' => 'Thrall']);
        $newCharacter = Character::factory()->main()->forGameVersion($gameVersion)->create(['name' => 'Jaina']);
        $absence = PlannedAbsence::factory()->create([
            'character_id' => $originalCharacter->id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->patchJson(route('raiding.absences.update', $absence), [
            'game_version' => $gameVersion->id,
            'character' => $newCharacter->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('planned_absences', [
            'id' => $absence->id,
            'character_id' => $newCharacter->id,
        ]);
    }

    #[Test]
    public function update_can_set_end_date_to_null(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create([
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(7),
        ]);

        $response = $this->actingAs($user)->patchJson(route('raiding.absences.update', $absence), [
            'end_date' => null,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('planned_absences', [
            'id' => $absence->id,
            'end_date' => null,
        ]);
    }

    // ==================== update: Validation ====================

    #[Group('validation')]
    #[Test]
    public function update_validates_end_date_must_be_after_start_date(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['start_date' => now()->addDays(7)]);

        $response = $this->actingAs($user)->patchJson(route('raiding.absences.update', $absence), [
            'end_date' => now()->addDay()->format('Y-m-d'),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['end_date']);
    }

    #[Group('validation')]
    #[Test]
    public function update_validates_character_must_exist(): void
    {
        $user = User::factory()->officer()->create();
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($user)->patchJson(route('raiding.absences.update', $absence), [
            'game_version' => $gameVersion->id,
            'character' => 99999,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['character']);
    }

    #[Group('validation')]
    #[Test]
    public function update_rejects_an_alt(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $alt = Character::factory()->forGameVersion($gameVersion)->create(['is_main' => false]);
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $this->actingAs($this->officer())
            ->patch(route('raiding.absences.update', $absence), ['game_version' => $gameVersion->id, 'character' => $alt->id])
            ->assertSessionHasErrors(['character' => 'Choose a main character.']);

        $this->assertNotSame($alt->id, $absence->fresh()->character_id);
    }

    #[Group('validation')]
    #[Test]
    public function update_rejects_a_character_from_another_version(): void
    {
        [$newer, $older] = $this->twoReleasedVersions();
        $main = Character::factory()->main()->forGameVersion($older)->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $this->actingAs($this->officer())
            ->patch(route('raiding.absences.update', $absence), ['game_version' => $newer->id, 'character' => $main->id])
            ->assertSessionHasErrors(['character' => 'Choose a main character.']);

        $this->assertNotSame($main->id, $absence->fresh()->character_id);
    }

    #[Group('validation')]
    #[Test]
    public function update_requires_a_game_version_with_a_character(): void
    {
        $main = $this->mainInReleasedVersion();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $this->actingAs($this->officer())
            ->patch(route('raiding.absences.update', $absence), ['character' => $main->id])
            ->assertSessionHasErrors(['game_version' => 'A game version is required.']);
    }

    // ==================== destroy: Access Control ====================

    #[Group('authorization')]
    #[Test]
    public function destroy_requires_authentication(): void
    {
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->deleteJson(route('raiding.absences.destroy', $absence));

        $response->assertUnauthorized();
    }

    #[Group('authorization')]
    #[Test]
    public function destroy_requires_authorization(): void
    {
        $member = $this->member();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['created_by' => $member->id, 'start_date' => today()->addWeek()]);

        $response = $this->actingAs($member)->deleteJson(route('raiding.absences.destroy', $absence));

        $response->assertForbidden();
        $this->assertNotSoftDeleted($absence);
    }

    // ==================== destroy: Happy Paths ====================

    #[Test]
    public function destroy_deletes_a_started_absence_with_permission(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['start_date' => today()->subWeek()]);

        $response = $this->actingAs($user)->delete(route('raiding.absences.destroy', $absence));

        $response->assertRedirect(route('raiding.absences.index'));
        $this->assertSoftDeleted('planned_absences', ['id' => $absence->id]);
    }

    // ==================== store/update: User Field ====================

    #[Test]
    public function store_assigns_absence_to_specified_existing_user_when_user_field_is_provided(): void
    {
        $officer = User::factory()->officer()->create();
        $targetUser = User::factory()->create();
        $character = $this->mainInReleasedVersion();

        $response = $this->actingAs($officer)->post(route('raiding.absences.store'), [
            'game_version' => $character->game_version_id,
            'character' => $character->id,
            'user' => $targetUser->id,
            'start_date' => now()->addDay()->format('Y-m-d'),
            'reason' => 'Away for work.',
        ]);

        $response->assertRedirectToRoute('raiding.absences.index');
        $this->assertDatabaseHas('planned_absences', [
            'character_id' => $character->id,
            'user_id' => $targetUser->id,
        ]);
    }

    #[Test]
    public function update_reassigns_absence_to_specified_user_when_user_field_is_provided(): void
    {
        $officer = User::factory()->officer()->create();
        $newUser = User::factory()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($officer)->patchJson(route('raiding.absences.update', $absence), [
            'user' => $newUser->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('planned_absences', [
            'id' => $absence->id,
            'user_id' => $newUser->id,
        ]);
    }

    #[Test]
    public function store_creates_user_from_discord_and_assigns_absence_when_user_not_in_database(): void
    {
        $this->mock(Discord::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getGuildMember')->once()->andReturn(GuildMember::from([
                'user' => ['id' => '999999999999999999', 'username' => 'discorduser', 'discriminator' => '0', 'avatar' => null, 'flags' => 0, 'public_flags' => 0],
                'nick' => 'DiscordNick',
                'avatar' => null,
                'banner' => null,
                'roles' => [],
                'deaf' => false,
                'mute' => false,
                'flags' => 0,
            ]));
        });

        $officer = User::factory()->officer()->create();
        $character = $this->mainInReleasedVersion();
        $unknownUserId = '999999999999999999';

        $response = $this->actingAs($officer)->post(route('raiding.absences.store'), [
            'game_version' => $character->game_version_id,
            'character' => $character->id,
            'user' => $unknownUserId,
            'start_date' => now()->addDay()->format('Y-m-d'),
            'reason' => 'Away.',
        ]);

        $response->assertRedirectToRoute('raiding.absences.index');
        $this->assertDatabaseHas('users', ['id' => $unknownUserId, 'username' => 'discorduser']);
        $this->assertDatabaseHas('planned_absences', [
            'character_id' => $character->id,
            'user_id' => $unknownUserId,
        ]);
    }

    // ==================== discord_members: Optional Prop ====================

    #[Test]
    public function create_does_not_search_discord_members_on_a_full_load(): void
    {
        $this->mock(Discord::class)->shouldNotReceive('searchGuildMembers');

        $this->actingAs($this->officer())
            ->get(route('raiding.absences.create', ['member_search' => 'test']))
            ->assertInertia(fn (Assert $page) => $page->missing('discord_members'));
    }

    #[Test]
    public function create_returns_discord_members_on_a_partial_reload(): void
    {
        $this->mock(Discord::class)
            ->shouldReceive('searchGuildMembers')
            ->once()
            ->with('test', 1)
            ->andReturn(collect([
                $this->fakeGuildMember('111111111111111111', 'testuser', 'TestNick'),
                $this->fakeGuildMember('333333333333333333', 'nonick'),
            ]));

        $this->actingAs($this->officer())
            ->get(route('raiding.absences.create', ['member_search' => 'test']))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('discord_members', fn (Assert $reload) => $reload
                    ->where('discord_members', [
                        ['id' => '111111111111111111', 'nickname' => 'TestNick', 'username' => 'testuser'],
                        ['id' => '333333333333333333', 'nickname' => null, 'username' => 'nonick'],
                    ])
                )
            );
    }

    #[Test]
    public function create_passes_the_member_limit_to_discord(): void
    {
        $this->mock(Discord::class)
            ->shouldReceive('searchGuildMembers')
            ->once()
            ->with('test', 5)
            ->andReturn(collect());

        $this->actingAs($this->officer())
            ->get(route('raiding.absences.create', ['member_search' => 'test', 'member_limit' => 5]))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('discord_members', fn (Assert $reload) => $reload->where('discord_members', []))
            );
    }

    #[Test]
    #[Group('validation')]
    public function create_requires_a_member_search_when_reloading_discord_members(): void
    {
        $this->mock(Discord::class)->shouldNotReceive('searchGuildMembers');

        $officer = $this->officer();
        $version = $this->actingAs($officer)->get(route('raiding.absences.create'))->viewData('page')['version'];

        $this->actingAs($officer)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) $version,
                'X-Inertia-Partial-Component' => 'Raiding/Absences/Form',
                'X-Inertia-Partial-Data' => 'discord_members',
            ])
            ->get(route('raiding.absences.create'))
            ->assertSessionHasErrors('member_search');
    }

    #[Test]
    public function create_caches_discord_member_searches(): void
    {
        // Serialize like Redis does, so cached objects come back as __PHP_Incomplete_Class under serializable_classes=false.
        config(['cache.stores.array.serialize' => true]);
        Cache::forgetDriver('array');

        $this->mock(Discord::class)
            ->shouldReceive('searchGuildMembers')
            ->once()
            ->andReturn(collect([$this->fakeGuildMember('444444444444444444', 'cacheduser', 'Cached')]));

        $officer = $this->officer();
        $expected = [['id' => '444444444444444444', 'nickname' => 'Cached', 'username' => 'cacheduser']];

        foreach (range(1, 2) as $attempt) {
            $this->actingAs($officer)
                ->get(route('raiding.absences.create', ['member_search' => 'cach']))
                ->assertInertia(fn (Assert $page) => $page
                    ->reloadOnly('discord_members', fn (Assert $reload) => $reload->where('discord_members', $expected))
                );
        }
    }

    #[Test]
    public function edit_returns_discord_members_on_a_partial_reload(): void
    {
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $this->mock(Discord::class)
            ->shouldReceive('searchGuildMembers')
            ->once()
            ->andReturn(collect([$this->fakeGuildMember('222222222222222222', 'guildofficer', 'Guild Officer')]));

        $this->actingAs($this->officer())
            ->get(route('raiding.absences.edit', [$absence, 'member_search' => 'officer']))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('discord_members', fn (Assert $reload) => $reload->where('discord_members', [
                    ['id' => '222222222222222222', 'nickname' => 'Guild Officer', 'username' => 'guildofficer'],
                ]))
            );
    }

    // ==================== helpers ====================

    private function fakeGuildMember(string $userId, string $username, ?string $nickname = null): GuildMember
    {
        return GuildMember::from([
            'user' => ['id' => $userId, 'username' => $username, 'discriminator' => '0', 'flags' => 0, 'public_flags' => 0],
            'nick' => $nickname,
            'roles' => [],
            'deaf' => false,
            'mute' => false,
            'flags' => 0,
        ]);
    }

    private function officer(): User
    {
        return User::factory()->officer()->create();
    }

    /**
     * A member who can log their own absences but not anyone else's.
     */
    private function member(): User
    {
        $role = DiscordRole::firstOrCreate(
            ['id' => '1265247017215594496'],
            ['name' => 'Raider', 'position' => 4, 'is_visible' => true]
        );
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'create-planned-absences', 'guard_name' => 'web']));

        return User::factory()->raider()->create();
    }

    /**
     * @return array{0: GameVersion, 1: GameVersion} The newer version, then the older one
     */
    private function twoReleasedVersions(): array
    {
        return [
            GameVersion::factory()->fetchableRoster()->create(['release_date' => now()->subWeek()]),
            GameVersion::factory()->fetchableRoster()->create(['release_date' => now()->subYear()]),
        ];
    }

    private function mainInReleasedVersion(): Character
    {
        return Character::factory()->main()->forGameVersion(GameVersion::factory()->fetchableRoster()->create())->create();
    }

    /**
     * @return array{game_version: int, character: int, start_date: string, reason: string}
     */
    private function absencePayload(GameVersion $gameVersion, Character $character): array
    {
        return [
            'game_version' => $gameVersion->id,
            'character' => $character->id,
            'start_date' => now()->addDay()->format('Y-m-d'),
            'reason' => 'Away.',
        ];
    }
}
