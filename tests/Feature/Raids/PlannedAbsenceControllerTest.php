<?php

namespace Tests\Feature\Raids;

use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\PlannedAbsence;
use App\Models\User;
use App\Services\Discord\Discord;
use App\Services\Discord\Resources\GuildMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

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
        $response = $this->get(route('raids.absences.index'));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function index_allows_authenticated_user(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('raids.absences.index'));

        $response->assertOk();
    }

    // ==================== index: Inertia Response ====================

    #[Test]
    public function index_renders_correct_inertia_component(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('raids.absences.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/PlannedAbsences/Index')
        );
    }

    // ==================== index: Deferred Props ====================

    #[Test]
    public function index_returns_empty_collection_when_no_absences_exist(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('raids.absences.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/PlannedAbsences/Index')
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

        $response = $this->actingAs($user)->get(route('raids.absences.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/PlannedAbsences/Index')
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

        $response = $this->actingAs($user)->get(route('raids.absences.index'));

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
        $response = $this->get(route('raids.absences.create'));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function create_returns_403_without_create_permission(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('raids.absences.create'));

        $response->assertForbidden();
    }

    #[Test]
    public function create_allows_user_with_create_permission(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.absences.create'));

        $response->assertOk();
    }

    // ==================== create: Inertia Response ====================

    #[Test]
    public function create_renders_correct_inertia_component(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.absences.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/PlannedAbsences/Form')
        );
    }

    #[Test]
    public function create_passes_only_main_characters_to_page(): void
    {
        $main = Character::factory()->create(['is_main' => true]);
        Character::factory()->create(['is_main' => false]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.absences.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/PlannedAbsences/Form')
            ->has('characters', 1)
            ->where('characters.0.id', $main->id)
        );
    }

    // ==================== create: action prop ====================

    #[Test]
    public function create_passes_action_url_to_page(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.absences.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('action', route('raids.absences.store'))
        );
    }

    // ==================== create: Resolved Character ====================

    #[Test]
    public function create_passes_null_resolved_character_when_user_has_create_for_others_permission(): void
    {
        $user = User::factory()->officer()->create(['nickname' => 'Aragorn']);
        Character::factory()->create(['name' => 'Aragorn', 'is_main' => true]);

        $response = $this->actingAs($user)->get(route('raids.absences.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('resolved_character', null)
        );
    }

    #[Test]
    public function create_resolves_character_from_user_nickname(): void
    {
        $role = DiscordRole::firstOrCreate(
            ['id' => '829022020301094923'],
            ['name' => 'Raider', 'position' => 2, 'is_visible' => true]
        );
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'create-planned-absences', 'guard_name' => 'web']));

        $user = User::factory()->create(['nickname' => 'Aragorn']);
        $user->discordRoles()->sync([$role->id]);

        $character = Character::factory()->create(['name' => 'Aragorn', 'is_main' => true]);

        $response = $this->actingAs($user)->get(route('raids.absences.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('resolved_character.id', $character->id)
            ->where('resolved_character.name', 'Aragorn')
        );
    }

    #[Test]
    public function create_passes_null_resolved_character_when_no_character_matches_nickname(): void
    {
        $role = DiscordRole::firstOrCreate(
            ['id' => '829022020301094923'],
            ['name' => 'Raider', 'position' => 2, 'is_visible' => true]
        );
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'create-planned-absences', 'guard_name' => 'web']));

        $user = User::factory()->create(['nickname' => 'Gandalf']);
        $user->discordRoles()->sync([$role->id]);

        Character::factory()->create(['name' => 'Aragorn', 'is_main' => true]);

        $response = $this->actingAs($user)->get(route('raids.absences.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('resolved_character', null)
        );
    }

    #[Test]
    public function create_resolves_character_from_first_word_of_nickname(): void
    {
        $role = DiscordRole::firstOrCreate(
            ['id' => '829022020301094923'],
            ['name' => 'Raider', 'position' => 2, 'is_visible' => true]
        );
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'create-planned-absences', 'guard_name' => 'web']));

        $user = User::factory()->create(['nickname' => 'Marktführer (Testsieger)']);
        $user->discordRoles()->sync([$role->id]);

        $character = Character::factory()->create(['name' => 'Marktführer', 'is_main' => true]);

        $response = $this->actingAs($user)->get(route('raids.absences.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('resolved_character.id', $character->id)
            ->where('resolved_character.name', 'Marktführer')
        );
    }

    // ==================== edit: Access Control ====================

    #[Test]
    public function edit_requires_authentication(): void
    {
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->get(route('raids.absences.edit', $absence));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function edit_requires_authorization(): void
    {
        $member = User::factory()->member()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($member)->get(route('raids.absences.edit', $absence));

        $response->assertForbidden();
    }

    #[Test]
    public function edit_allows_owner_without_update_permission(): void
    {
        $owner = User::factory()->member()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['created_by' => $owner->id]);

        $response = $this->actingAs($owner)->get(route('raids.absences.edit', $absence));

        $response->assertOk();
    }

    #[Test]
    public function edit_allows_user_with_update_permission(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($user)->get(route('raids.absences.edit', $absence));

        $response->assertOk();
    }

    // ==================== edit: Inertia Response ====================

    #[Test]
    public function edit_renders_correct_inertia_component(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($user)->get(route('raids.absences.edit', $absence));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/PlannedAbsences/Form')
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

        $response = $this->actingAs($user)->get(route('raids.absences.edit', $absence));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/PlannedAbsences/Form')
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
    public function edit_passes_only_main_characters_to_page(): void
    {
        $main = Character::factory()->create(['is_main' => true]);
        Character::factory()->create(['is_main' => false]);

        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->create(['character_id' => $main->id, 'created_by' => $user->id]);

        $response = $this->actingAs($user)->get(route('raids.absences.edit', $absence));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('characters', 1)
            ->where('characters.0.id', $main->id)
        );
    }

    #[Test]
    public function edit_passes_action_url_to_page(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($user)->get(route('raids.absences.edit', $absence));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('action', route('raids.absences.update', $absence))
        );
    }

    // ==================== edit: Resolved Character ====================

    #[Test]
    public function edit_passes_null_resolved_character_when_user_has_create_for_others_permission(): void
    {
        $user = User::factory()->officer()->create(['nickname' => 'Aragorn']);
        Character::factory()->create(['name' => 'Aragorn', 'is_main' => true]);
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($user)->get(route('raids.absences.edit', $absence));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('resolved_character', null)
        );
    }

    #[Test]
    public function edit_resolves_character_from_user_nickname(): void
    {
        $owner = User::factory()->member()->create(['nickname' => 'Aragorn']);
        $character = Character::factory()->create(['name' => 'Aragorn', 'is_main' => true]);
        $absence = PlannedAbsence::factory()->create([
            'character_id' => $character->id,
            'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)->get(route('raids.absences.edit', $absence));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('resolved_character.id', $character->id)
            ->where('resolved_character.name', 'Aragorn')
        );
    }

    #[Test]
    public function edit_passes_null_resolved_character_when_no_character_matches_nickname(): void
    {
        $owner = User::factory()->member()->create(['nickname' => 'Gandalf']);
        $absence = PlannedAbsence::factory()->withCharacter()->create(['created_by' => $owner->id]);

        Character::factory()->create(['name' => 'Aragorn', 'is_main' => true]);

        $response = $this->actingAs($owner)->get(route('raids.absences.edit', $absence));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('resolved_character', null)
        );
    }

    #[Test]
    public function edit_resolves_character_from_first_word_of_nickname(): void
    {
        $owner = User::factory()->member()->create(['nickname' => 'Marktführer (Testsieger)']);
        $character = Character::factory()->create(['name' => 'Marktführer', 'is_main' => true]);
        $absence = PlannedAbsence::factory()->create([
            'character_id' => $character->id,
            'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)->get(route('raids.absences.edit', $absence));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('resolved_character.id', $character->id)
        );
    }

    // ==================== store: Access Control ====================

    #[Test]
    public function store_requires_authentication(): void
    {
        $response = $this->postJson(route('raids.absences.store'));

        $response->assertUnauthorized();
    }

    #[Test]
    public function store_requires_authorization(): void
    {
        $user = User::factory()->member()->create();
        $character = Character::factory()->main()->create();

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
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
        $character = Character::factory()->main()->create();

        $startDate = now()->addDay();
        $endDate = now()->addDays(7);

        $response = $this->actingAs($user)->post(route('raids.absences.store'), [
            'character' => $character->id,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'reason' => 'Going on holiday.',
        ]);

        $response->assertRedirectToRoute('raids.absences.index');
        $this->assertDatabaseHas('planned_absences', [
            'character_id' => $character->id,
            'start_date' => $startDate->format('Y-m-d').' 00:00:00',
            'end_date' => $endDate->format('Y-m-d').' 00:00:00',
            'created_by' => $user->id,
        ]);
    }

    #[Test]
    public function store_creates_absence_with_character_name(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->main()->create(['name' => 'Aragorn']);

        $response = $this->actingAs($user)->post(route('raids.absences.store'), [
            'character' => 'Aragorn',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'reason' => 'Scouting the Misty Mountains.',
        ]);

        $response->assertRedirectToRoute('raids.absences.index');
        $this->assertDatabaseHas('planned_absences', ['character_id' => $character->id]);
    }

    #[Test]
    public function store_creates_absence_with_name_matching_diacritics(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->main()->create(['name' => 'Déo']);

        $response = $this->actingAs($user)->post(route('raids.absences.store'), [
            'character' => 'Deo',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'reason' => 'Away for a week.',
        ]);

        $response->assertRedirectToRoute('raids.absences.index');
        $this->assertDatabaseHas('planned_absences', ['character_id' => $character->id]);
    }

    #[Test]
    public function store_creates_absence_without_end_date(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->main()->create();

        $response = $this->actingAs($user)->post(route('raids.absences.store'), [
            'character' => $character->id,
            'start_date' => now()->addDay()->format('Y-m-d'),
            'reason' => 'Indefinite absence.',
        ]);

        $response->assertRedirectToRoute('raids.absences.index');
        $this->assertDatabaseHas('planned_absences', [
            'character_id' => $character->id,
            'end_date' => null,
        ]);
    }

    #[Test]
    public function store_redirects_to_account_when_user_cannot_view_absences(): void
    {
        $role = DiscordRole::firstOrCreate(
            ['id' => '829022020301094923'],
            ['name' => 'Raider', 'position' => 2, 'is_visible' => true]
        );
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'create-planned-absences', 'guard_name' => 'web']));

        $user = User::factory()->create();
        $user->discordRoles()->sync([$role->id]);

        $character = Character::factory()->main()->create();

        $response = $this->actingAs($user)->post(route('raids.absences.store'), [
            'character' => $character->id,
            'start_date' => now()->addDay()->format('Y-m-d'),
            'reason' => 'Away for a week.',
        ]);

        $response->assertRedirectToRoute('account.index');
        $this->assertDatabaseHas('planned_absences', ['character_id' => $character->id]);
    }

    // ==================== store: Special Responses ====================

    #[Test]
    public function store_returns_300_when_multiple_characters_match_name(): void
    {
        $user = User::factory()->officer()->create();
        Character::factory()->main()->create(['name' => 'Déo']);
        Character::factory()->main()->create(['name' => 'Deò']);

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
            'character' => 'Deo',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'reason' => 'Away for a week.',
        ]);

        $response->assertStatus(300);
        $response->assertJsonStructure(['message', 'characters' => [['id', 'name']]]);
        $this->assertCount(2, $response->json('characters'));
    }

    #[Test]
    public function store_returns_400_when_character_is_not_main(): void
    {
        $user = User::factory()->officer()->create();
        $mainCharacter = Character::factory()->main()->create(['name' => 'Mainchar']);
        $altCharacter = Character::factory()->create(['name' => 'Altchar', 'is_main' => false]);
        DB::table('character_links')->insert([
            'linked_character_id' => $altCharacter->id,
            'character_id' => $mainCharacter->id,
        ]);

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
            'character' => 'Altchar',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'reason' => 'Away for a week.',
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('message', 'The specified character is not a main character.');
        $response->assertJsonPath('suggestion', 'Mainchar');
    }

    #[Test]
    public function store_returns_400_with_null_suggestion_when_no_main_exists(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->create(['name' => 'Altchar', 'is_main' => false]);

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
            'character' => $character->id,
            'start_date' => now()->addDay()->format('Y-m-d'),
            'reason' => 'Away for a week.',
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('suggestion', null);
    }

    // ==================== store: Validation ====================

    #[Test]
    public function store_requires_character(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
            'start_date' => now()->addDay()->format('Y-m-d'),
            'reason' => 'Away for a week.',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['character']);
    }

    #[Test]
    public function store_requires_start_date(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->main()->create();

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
            'character' => $character->id,
            'reason' => 'Away for a week.',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['start_date']);
    }

    #[Test]
    public function store_requires_reason(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->main()->create();

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
            'character' => $character->id,
            'start_date' => now()->addDay()->format('Y-m-d'),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['reason']);
    }

    #[Test]
    public function store_validates_end_date_must_be_after_start_date(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->main()->create();

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
            'character' => $character->id,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->addDay()->format('Y-m-d'),
            'reason' => 'Away for a week.',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['end_date']);
    }

    #[Test]
    public function store_validates_character_name_max_length(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
            'character' => 'TwelveCharsX',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'reason' => 'Away for a week.',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['character']);
    }

    #[Test]
    public function store_validates_character_name_no_spaces(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
            'character' => 'Two Words',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'reason' => 'Away for a week.',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['character']);
    }

    #[Test]
    public function store_validates_character_name_no_numbers(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
            'character' => 'Char1',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'reason' => 'Away for a week.',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['character']);
    }

    // ==================== update: Access Control ====================

    #[Test]
    public function update_requires_authentication(): void
    {
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->patchJson(route('raids.absences.update', $absence));

        $response->assertUnauthorized();
    }

    #[Test]
    public function update_requires_authorization(): void
    {
        $member = User::factory()->member()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($member)->patchJson(route('raids.absences.update', $absence), [
            'reason' => 'Updated reason.',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function update_forbids_character_change_without_update_permission(): void
    {
        $owner = User::factory()->member()->create();
        $newCharacter = Character::factory()->main()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['created_by' => $owner->id]);

        $response = $this->actingAs($owner)->patchJson(route('raids.absences.update', $absence), [
            'character' => $newCharacter->id,
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

        $response = $this->actingAs($user)->patchJson(route('raids.absences.update', $absence), [
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

        $response = $this->actingAs($user)->patchJson(route('raids.absences.update', $absence), [
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

        $response = $this->actingAs($user)->patchJson(route('raids.absences.update', $absence), [
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
        $originalCharacter = Character::factory()->main()->create();
        $newCharacter = Character::factory()->main()->create();
        $absence = PlannedAbsence::factory()->create([
            'character_id' => $originalCharacter->id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->patchJson(route('raids.absences.update', $absence), [
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

        $response = $this->actingAs($user)->patchJson(route('raids.absences.update', $absence), [
            'end_date' => null,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('planned_absences', [
            'id' => $absence->id,
            'end_date' => null,
        ]);
    }

    #[Test]
    public function update_owner_can_update_non_character_fields_without_permission(): void
    {
        $owner = User::factory()->member()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create([
            'created_by' => $owner->id,
            'reason' => 'Old reason.',
        ]);

        $response = $this->actingAs($owner)->patchJson(route('raids.absences.update', $absence), [
            'reason' => 'Updated reason.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('planned_absences', [
            'id' => $absence->id,
            'reason' => 'Updated reason.',
        ]);
    }

    // ==================== update: Special Responses ====================

    #[Test]
    public function update_returns_400_when_character_is_not_main(): void
    {
        $user = User::factory()->officer()->create();
        $altCharacter = Character::factory()->create(['is_main' => false]);
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($user)->patchJson(route('raids.absences.update', $absence), [
            'character' => $altCharacter->id,
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('message', 'The specified character is not a main character.');
    }

    // ==================== update: Validation ====================

    #[Test]
    public function update_validates_end_date_must_be_after_start_date(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['start_date' => now()->addDays(7)]);

        $response = $this->actingAs($user)->patchJson(route('raids.absences.update', $absence), [
            'end_date' => now()->addDay()->format('Y-m-d'),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['end_date']);
    }

    #[Test]
    public function update_validates_character_must_exist(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($user)->patchJson(route('raids.absences.update', $absence), [
            'character' => 99999,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['character']);
    }

    // ==================== destroy: Access Control ====================

    #[Test]
    public function destroy_requires_authentication(): void
    {
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->deleteJson(route('raids.absences.destroy', $absence));

        $response->assertUnauthorized();
    }

    #[Test]
    public function destroy_requires_authorization(): void
    {
        $member = User::factory()->member()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($member)->deleteJson(route('raids.absences.destroy', $absence));

        $response->assertForbidden();
    }

    // ==================== destroy: Happy Paths ====================

    #[Test]
    public function destroy_deletes_absence_with_permission(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($user)->deleteJson(route('raids.absences.destroy', $absence));

        $response->assertRedirect();
        $this->assertSoftDeleted('planned_absences', ['id' => $absence->id]);
    }

    #[Test]
    public function destroy_owner_can_delete_their_own_absence(): void
    {
        $owner = User::factory()->member()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['created_by' => $owner->id]);

        $response = $this->actingAs($owner)->deleteJson(route('raids.absences.destroy', $absence));

        $response->assertRedirect();
        $this->assertSoftDeleted('planned_absences', ['id' => $absence->id]);
    }

    #[Test]
    public function destroy_owner_cannot_delete_another_users_absence(): void
    {
        $owner = User::factory()->member()->create();
        $other = User::factory()->member()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['created_by' => $other->id]);

        $response = $this->actingAs($owner)->deleteJson(route('raids.absences.destroy', $absence));

        $response->assertForbidden();
        $this->assertDatabaseHas('planned_absences', ['id' => $absence->id]);
    }

    // ==================== create: Null Nickname ====================

    #[Test]
    public function create_passes_null_resolved_character_when_user_has_no_nickname(): void
    {
        $role = DiscordRole::firstOrCreate(
            ['id' => '829022020301094923'],
            ['name' => 'Raider', 'position' => 2, 'is_visible' => true]
        );
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'create-planned-absences', 'guard_name' => 'web']));

        $user = User::factory()->create(['nickname' => null]);
        $user->discordRoles()->sync([$role->id]);

        $response = $this->actingAs($user)->get(route('raids.absences.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('resolved_character', null)
        );
    }

    // ==================== store: Character Not Found ====================

    #[Test]
    public function store_redirects_back_with_error_when_character_name_matches_nothing(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('raids.absences.store'), [
            'character' => 'Zzyzx',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'reason' => 'Gone.',
        ]);

        $response->assertSessionHasErrors(['character']);
    }

    // ==================== store/update: User Field ====================

    #[Test]
    public function store_assigns_absence_to_specified_existing_user_when_user_field_is_provided(): void
    {
        $officer = User::factory()->officer()->create();
        $targetUser = User::factory()->create();
        $character = Character::factory()->main()->create();

        $response = $this->actingAs($officer)->post(route('raids.absences.store'), [
            'character' => $character->id,
            'user' => $targetUser->id,
            'start_date' => now()->addDay()->format('Y-m-d'),
            'reason' => 'Away for work.',
        ]);

        $response->assertRedirectToRoute('raids.absences.index');
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

        $response = $this->actingAs($officer)->patchJson(route('raids.absences.update', $absence), [
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
                'user' => ['id' => '999999999999999999', 'username' => 'discorduser', 'discriminator' => '0', 'avatar' => null],
                'nick' => 'DiscordNick',
                'avatar' => null,
                'banner' => null,
                'roles' => [],
            ]));
        });

        $officer = User::factory()->officer()->create();
        $character = Character::factory()->main()->create();
        $unknownUserId = '999999999999999999';

        $response = $this->actingAs($officer)->post(route('raids.absences.store'), [
            'character' => $character->id,
            'user' => $unknownUserId,
            'start_date' => now()->addDay()->format('Y-m-d'),
            'reason' => 'Away.',
        ]);

        $response->assertRedirectToRoute('raids.absences.index');
        $this->assertDatabaseHas('users', ['id' => $unknownUserId, 'username' => 'discorduser']);
        $this->assertDatabaseHas('planned_absences', [
            'character_id' => $character->id,
            'user_id' => $unknownUserId,
        ]);
    }
}
