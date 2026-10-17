<?php

namespace Tests\Feature\Raiding;

use App\Actions\Characters\RefreshCharacterPortrait;
use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Data\PlannedAbsenceReasonData;
use App\Enums\PlannedAbsenceReason;
use App\Events\Broadcasts\DiscordMembersSearched;
use App\Http\Integrations\Discord\Requests\Guilds\SearchGuildMembersRequest;
use App\Jobs\Discord\SearchGuildMembers;
use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\GameVersion;
use App\Models\Permission;
use App\Models\PlannedAbsence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('raiding')]
#[Group('discord-integration')]
class PlannedAbsenceControllerTest extends TestCase
{
    use MocksDiscordService;
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
        $backdatePermission = Permission::firstOrCreate(['name' => 'backdate-planned-absences', 'guard_name' => 'web']);
        $updatePermission = Permission::firstOrCreate(['name' => 'update-planned-absences', 'guard_name' => 'web']);
        $deletePermission = Permission::firstOrCreate(['name' => 'delete-planned-absences', 'guard_name' => 'web']);

        $officerRole->givePermissionTo($viewPermission);
        $officerRole->givePermissionTo($createPermission);
        $officerRole->givePermissionTo($backdatePermission);
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
                ->has('planned_absences.data', 0)
                ->has('past_planned_absences', 0)
            )
        );
    }

    #[Test]
    public function index_deferred_planned_absences_returns_collection_with_expected_structure(): void
    {
        $user = User::factory()->member()->create();
        PlannedAbsence::factory()->withCharacter()->create(['start_date' => today()->addDay(), 'end_date' => today()->addDays(3)]);

        $response = $this->actingAs($user)->get(route('raiding.absences.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/Absences/Index')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('planned_absences.data', 1)
                ->has('planned_absences.data.0', fn (Assert $absence) => $absence
                    ->has('id')
                    ->has('character')
                    ->has('start_date')
                    ->has('end_date')
                    ->has('reason', fn (Assert $reason) => $reason->hasAll(['key', 'label', 'description', 'icon']))
                    ->has('notes')
                    ->has('created_by')
                    ->has('created_at')
                    ->has('updated_at')
                    ->has('deleted_at')
                    ->has('meta', fn (Assert $meta) => $meta->hasAll(['has_started', 'has_ended']))
                    ->missing('permissions')
                )
                ->has('planned_absences.permissions', fn (Assert $permissions) => $permissions->hasAll(['update', 'delete', 'restore']))
            )
        );
    }

    #[Test]
    public function index_orders_absences_by_start_date(): void
    {
        $user = User::factory()->member()->create();

        $later = PlannedAbsence::factory()->withCharacter()->create([
            'start_date' => now()->addDays(5),
            'end_date' => null,
        ]);
        $sooner = PlannedAbsence::factory()->withCharacter()->create([
            'start_date' => now()->addDay(),
            'end_date' => null,
        ]);

        $response = $this->actingAs($user)->get(route('raiding.absences.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('planned_absences.data', 2)
                ->where('planned_absences.data.0.id', $sooner->id)
                ->where('planned_absences.data.1.id', $later->id)
            )
        );
    }

    #[Test]
    public function index_includes_character_portrait_url(): void
    {
        Storage::fake('public');
        $character = Character::factory()->create();
        $character->addMediaFromString('BINARY')
            ->usingFileName('character_15678.jpg')
            ->toMediaCollection(Character::MEDIA_COLLECTION);
        PlannedAbsence::factory()->create([
            'character_id' => $character->id,
            'start_date' => today()->addDay(),
            'end_date' => today()->addDays(3),
        ]);

        $this->actingAs(User::factory()->member()->create())
            ->get(route('raiding.absences.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('planned_absences.data.0.character.portrait_url', $character->getFirstMediaUrl(Character::MEDIA_COLLECTION))
                )
            );
    }

    #[Test]
    public function index_queues_one_portrait_refresh_per_absent_character(): void
    {
        Queue::fake();
        $character = $this->mainInReleasedVersion();
        PlannedAbsence::factory()->count(2)->create([
            'character_id' => $character->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(3),
        ]);

        $this->actingAs(User::factory()->member()->create())
            ->get(route('raiding.absences.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload->has('planned_absences.data', 2))
            );

        RefreshCharacterPortrait::assertPushed(1);
        RefreshCharacterPortrait::assertPushed(fn (RefreshCharacterPortrait $action, array $parameters): bool => $parameters[0]->is($character));
    }

    #[Test]
    public function index_does_not_refresh_portraits_for_past_or_deleted_absences(): void
    {
        Queue::fake();
        PlannedAbsence::factory()->create([
            'character_id' => $this->mainInReleasedVersion()->id,
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDays(5),
        ]);
        PlannedAbsence::factory()->create([
            'character_id' => $this->mainInReleasedVersion()->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(3),
        ])->delete();

        $this->actingAs(User::factory()->member()->create())
            ->get(route('raiding.absences.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('planned_absences.data', 1)
                    ->has('past_planned_absences', 1)
                )
            );

        RefreshCharacterPortrait::assertNotPushed();
    }

    #[Test]
    public function index_reflects_new_current_and_upcoming_absences_without_cache_flush(): void
    {
        $user = User::factory()->member()->create();
        PlannedAbsence::factory()->withCharacter()->create(['start_date' => today()->addDay(), 'end_date' => today()->addDays(3)]);

        $this->actingAs($user)
            ->get(route('raiding.absences.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload->has('planned_absences.data', 1))
            );

        PlannedAbsence::withoutEvents(fn () => PlannedAbsence::factory()->withCharacter()->create(['start_date' => today(), 'end_date' => null]));

        $this->actingAs($user)
            ->get(route('raiding.absences.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload->has('planned_absences.data', 2))
            );
    }

    // ==================== index: Past Absences ====================

    #[Test]
    public function index_splits_ended_absences_into_the_past_prop(): void
    {
        $past = $this->pastAbsence();
        $endsToday = PlannedAbsence::factory()->withCharacter()->create(['start_date' => today()->subDays(2), 'end_date' => today()]);

        $this->actingAs(User::factory()->member()->create())
            ->get(route('raiding.absences.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('planned_absences.data', 1)
                    ->where('planned_absences.data.0.id', $endsToday->id)
                    ->has('past_planned_absences', 1)
                    ->where('past_planned_absences.0.id', $past->id)
                    ->has('past_planned_absences.0.character.portrait_url')
                    ->missing('past_planned_absences.0.permissions')
                )
            );
    }

    #[Test]
    public function index_lists_an_open_ended_absence_that_started_a_month_ago_as_current(): void
    {
        $this->freezeTime();
        $openEnded = PlannedAbsence::factory()->withCharacter()->create(['start_date' => today()->subMonth(), 'end_date' => null]);

        $this->actingAs(User::factory()->member()->create())
            ->get(route('raiding.absences.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('planned_absences.data', 1)
                    ->where('planned_absences.data.0.id', $openEnded->id)
                    ->has('past_planned_absences', 0)
                )
            );
    }

    #[Test]
    public function index_includes_deleted_past_absences(): void
    {
        $this->pastAbsence()->delete();

        $this->actingAs(User::factory()->member()->create())
            ->get(route('raiding.absences.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('past_planned_absences', 1)
                    ->whereNot('past_planned_absences.0.deleted_at', null)
                )
            );
    }

    #[Test]
    public function index_serves_past_absences_from_cache_until_the_end_of_the_day(): void
    {
        $user = User::factory()->member()->create();
        $this->pastAbsence();

        $this->actingAs($user)
            ->get(route('raiding.absences.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload->has('past_planned_absences', 1))
            );

        PlannedAbsence::withoutEvents(fn () => $this->pastAbsence());

        $this->actingAs($user)
            ->get(route('raiding.absences.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload->has('past_planned_absences', 1))
            );

        $this->travelTo(today()->addDay()->addMinute());

        $this->actingAs($user)
            ->get(route('raiding.absences.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload->has('past_planned_absences', 2))
            );
    }

    #[Test]
    public function index_grants_officer_permissions_without_restore(): void
    {
        $this->actingAs($this->officer())
            ->get(route('raiding.absences.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('planned_absences.permissions', ['update' => true, 'delete' => true, 'restore' => false])
                )
            );
    }

    #[Test]
    public function index_grants_admins_every_permission(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('raiding.absences.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('planned_absences.permissions', ['update' => true, 'delete' => true, 'restore' => true])
                )
            );
    }

    #[Test]
    public function index_does_not_cache_permissions_when_a_member_loads_first(): void
    {
        $this->pastAbsence();

        $this->assertIndexPermissions(User::factory()->member()->create(), ['update' => false, 'delete' => false, 'restore' => false]);
        $this->assertIndexPermissions(User::factory()->admin()->create(), ['update' => true, 'delete' => true, 'restore' => true]);
    }

    #[Test]
    public function index_does_not_cache_permissions_when_an_admin_loads_first(): void
    {
        $this->pastAbsence();

        $this->assertIndexPermissions(User::factory()->admin()->create(), ['update' => true, 'delete' => true, 'restore' => true]);
        $this->assertIndexPermissions(User::factory()->member()->create(), ['update' => false, 'delete' => false, 'restore' => false]);
    }

    // ==================== index: Character Portraits ====================

    #[Test]
    public function index_does_not_load_character_portraits_until_requested(): void
    {
        PlannedAbsence::factory()->withCharacter()->create(['start_date' => today()->addDay(), 'end_date' => today()->addDays(3)]);

        $this->actingAs(User::factory()->member()->create())
            ->get(route('raiding.absences.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('character_portraits')
                ->loadDeferredProps(fn (Assert $reload) => $reload->missing('character_portraits'))
            );
    }

    #[Test]
    public function index_character_portraits_maps_current_and_upcoming_characters_to_their_portraits(): void
    {
        Queue::fake();
        Storage::fake('public');
        $withPortrait = Character::factory()->create();
        $withPortrait->addMediaFromString('BINARY')
            ->usingFileName('character_15678.jpg')
            ->toMediaCollection(Character::MEDIA_COLLECTION);
        $withoutPortrait = Character::factory()->create();
        $pastOnly = Character::factory()->create();
        $deletedOnly = Character::factory()->create();

        PlannedAbsence::factory()->create(['character_id' => $withPortrait->id, 'start_date' => today()->subDay(), 'end_date' => today()->addDay()]);
        PlannedAbsence::factory()->create(['character_id' => $withoutPortrait->id, 'start_date' => today()->addDay(), 'end_date' => null]);
        $this->pastAbsence(['character_id' => $pastOnly->id]);
        PlannedAbsence::factory()->create(['character_id' => $deletedOnly->id, 'start_date' => today()->addDay(), 'end_date' => today()->addDays(2)])->delete();

        $this->actingAs(User::factory()->member()->create())
            ->get(route('raiding.absences.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('character_portraits', fn (Assert $reload) => $reload
                    ->where('character_portraits', [
                        $withPortrait->id => $withPortrait->getFirstMediaUrl(Character::MEDIA_COLLECTION),
                        $withoutPortrait->id => null,
                    ])
                    ->missing('planned_absences')
                    ->missing('past_planned_absences')
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
    public function create_returns_403_without_view_permission(): void
    {
        $user = $this->member();

        $response = $this->actingAs($user)->get(route('raiding.absences.create'));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function create_returns_403_without_create_permission(): void
    {
        $user = $this->userWithPermissions(['view-planned-absences', 'backdate-planned-absences']);

        $response = $this->actingAs($user)->get(route('raiding.absences.create'));

        $response->assertForbidden();
    }

    #[Test]
    public function create_allows_user_with_view_and_create_permissions(): void
    {
        $user = $this->userWithPermissions(['view-planned-absences', 'create-planned-absences']);

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
                ->where('game_version.data.id', $current->id)
                ->has('game_versions.data', 2)
                ->where('characters.data', fn ($characters) => collect($characters)->pluck('id')->all() === [$main->id]));
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
                ->where('game_version.data.id', $older->id)
                ->where('characters.data', fn ($characters) => collect($characters)->pluck('id')->all() === [$main->id]));
    }

    #[Test]
    public function create_falls_back_to_the_newest_released_version(): void
    {
        [$newer] = $this->twoReleasedVersions();

        $this->actingAs($this->officer())
            ->get(route('raiding.absences.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('game_version.data.id', $newer->id)
                ->where('game_versions.data.0.id', $newer->id));
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
                ->where('game_version.data.id', $newer->id)
                ->where('game_versions.data', fn ($gameVersions) => collect($gameVersions)->pluck('id')->all() === [$newer->id, $older->id]));
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
                ->where('characters.data', fn ($characters) => collect($characters)->pluck('id')->all() === [$olderMain->id]));
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
            ->assertInertia(fn (Assert $page) => $page->where('characters.data', []));

        $character->update(['is_main' => true]);

        $this->actingAs($officer)
            ->withSession([ResolveCurrentGameVersion::SESSION_KEY => $current->slug])
            ->get(route('raiding.absences.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('characters.data', fn ($characters) => collect($characters)->pluck('id')->all() === [$character->id]));
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
    public function create_passes_reason_options(): void
    {
        $user = User::factory()->officer()->create();

        $this->actingAs($user)
            ->get(route('raiding.absences.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('reasons', PlannedAbsenceReasonData::options()->toArray()));
    }

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

    #[Group('authorization')]
    #[Test]
    public function edit_returns_403_without_update_permission(): void
    {
        $user = $this->userWithPermissions(['view-planned-absences', 'backdate-planned-absences']);
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($user)->get(route('raiding.absences.edit', $absence));

        $response->assertForbidden();
    }

    #[Test]
    public function edit_allows_user_with_update_permission(): void
    {
        $user = $this->userWithPermissions(['view-planned-absences', 'update-planned-absences']);
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($user)->get(route('raiding.absences.edit', $absence));

        $response->assertOk();
    }

    // ==================== edit: Inertia Response ====================

    #[Test]
    public function edit_passes_reason_options(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $this->actingAs($user)
            ->get(route('raiding.absences.edit', $absence))
            ->assertInertia(fn (Assert $page) => $page
                ->where('reasons', PlannedAbsenceReasonData::options()->toArray()));
    }

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
            'reason' => PlannedAbsenceReason::Holiday,
        ]);

        $response = $this->actingAs($user)->get(route('raiding.absences.edit', $absence));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/Absences/Form')
            ->has('planned_absence.data', fn (Assert $data) => $data
                ->where('id', $absence->id)
                ->has('character')
                ->has('user')
                ->where('start_date', '2026-06-01')
                ->where('end_date', '2026-06-07')
                ->where('reason.key', 'On holiday')
                ->has('notes')
                ->has('created_by')
                ->has('created_at')
                ->has('updated_at')
                ->has('deleted_at')
                ->has('meta')
                ->missing('permissions')
            )
            ->where('planned_absence.permissions', ['edit' => true, 'delete' => true, 'restore' => false, 'backdate' => true])
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
                ->where('game_version.data.id', $older->id)
                ->missing('game_versions')
                ->where('characters.data', fn ($characters) => collect($characters)->pluck('id')->all() === [$character->id]));
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
            ->assertInertia(fn (Assert $page) => $page->where('game_version.data.id', $older->id));
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
    public function store_returns_403_without_view_permission(): void
    {
        $user = $this->member();
        $character = $this->mainInReleasedVersion();

        $response = $this->actingAs($user)->postJson(route('raiding.absences.store'), [
            'game_version' => $character->game_version_id,
            'game_version' => $character->game_version_id,
            'character' => $character->id,
            'start_date' => now()->addDay()->format('Y-m-d'),
            'reason' => PlannedAbsenceReason::Holiday->value,
        ]);

        $response->assertForbidden();
    }

    // ==================== store: Happy Paths ====================

    #[Test]
    public function store_saves_reason_and_notes(): void
    {
        $user = User::factory()->officer()->create();
        $character = $this->mainInReleasedVersion();

        $this->actingAs($user)
            ->post(route('raiding.absences.store'), [
                ...$this->absencePayload($character->gameVersion, $character),
                'reason' => PlannedAbsenceReason::Other->value,
                'notes' => 'Spoke to an officer.',
            ])
            ->assertRedirectToRoute('raiding.absences.index');

        $this->assertDatabaseHas('planned_absences', [
            'character_id' => $character->id,
            'reason' => PlannedAbsenceReason::Other->value,
            'notes' => 'Spoke to an officer.',
        ]);
    }

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
            'reason' => PlannedAbsenceReason::Holiday->value,
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
            'reason' => PlannedAbsenceReason::Holiday->value,
        ]);

        $response->assertRedirectToRoute('raiding.absences.index');
        $this->assertDatabaseHas('planned_absences', [
            'character_id' => $character->id,
            'end_date' => null,
        ]);
    }

    #[Test]
    public function store_accepts_an_end_date_on_the_start_date(): void
    {
        $user = User::factory()->officer()->create();
        $character = $this->mainInReleasedVersion();
        $date = now()->addDay()->format('Y-m-d');

        $response = $this->actingAs($user)->post(route('raiding.absences.store'), [
            'game_version' => $character->game_version_id,
            'character' => $character->id,
            'start_date' => $date,
            'end_date' => $date,
            'reason' => PlannedAbsenceReason::Holiday->value,
        ]);

        $response->assertRedirectToRoute('raiding.absences.index');
        $this->assertDatabaseHas('planned_absences', [
            'character_id' => $character->id,
            'end_date' => $date.' 00:00:00',
        ]);
    }

    // ==================== store: Validation ====================

    #[Test]
    #[Group('validation')]
    public function store_rejects_a_reason_outside_the_enum(): void
    {
        $user = User::factory()->officer()->create();
        $character = $this->mainInReleasedVersion();

        $this->actingAs($user)
            ->postJson(route('raiding.absences.store'), [
                ...$this->absencePayload($character->gameVersion, $character),
                'reason' => 'Travelling for work',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason' => 'Choose one of the listed reasons.']);
    }

    #[Group('validation')]
    #[Test]
    public function store_requires_character(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->postJson(route('raiding.absences.store'), [
            'start_date' => now()->addDay()->format('Y-m-d'),
            'reason' => PlannedAbsenceReason::Holiday->value,
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
            'reason' => PlannedAbsenceReason::Holiday->value,
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
    public function store_validates_end_date_must_not_be_before_start_date(): void
    {
        $user = User::factory()->officer()->create();
        $character = $this->mainInReleasedVersion();

        $response = $this->actingAs($user)->postJson(route('raiding.absences.store'), [
            'game_version' => $character->game_version_id,
            'character' => $character->id,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->addDay()->format('Y-m-d'),
            'reason' => PlannedAbsenceReason::Holiday->value,
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
                'reason' => PlannedAbsenceReason::Holiday->value,
            ])
            ->assertSessionHasErrors(['character' => 'Choose a main character.'])
            ->assertSessionDoesntHaveErrors('game_version');

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
            ->assertSessionHasErrors(['character' => "That game version isn't available."])
            ->assertSessionDoesntHaveErrors('game_version');

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
            'reason' => PlannedAbsenceReason::Holiday->value,
        ]);

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function update_returns_403_without_update_permission(): void
    {
        $user = $this->userWithPermissions(['view-planned-absences', 'backdate-planned-absences']);
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($user)->patchJson(route('raiding.absences.update', $absence), [
            'reason' => PlannedAbsenceReason::Holiday->value,
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function update_allows_user_with_update_permission(): void
    {
        $user = $this->userWithPermissions(['view-planned-absences', 'update-planned-absences']);
        $absence = PlannedAbsence::factory()->withCharacter()->create(['reason' => PlannedAbsenceReason::Other]);

        $response = $this->actingAs($user)->patch(route('raiding.absences.update', $absence), [
            'reason' => PlannedAbsenceReason::Holiday->value,
        ]);

        $response->assertRedirect(route('raiding.absences.index'));
        $this->assertDatabaseHas('planned_absences', [
            'id' => $absence->id,
            'reason' => PlannedAbsenceReason::Holiday->value,
        ]);
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
    public function update_accepts_an_end_date_on_the_stored_start_date(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create([
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(7),
        ]);

        $response = $this->actingAs($user)->patchJson(route('raiding.absences.update', $absence), [
            'end_date' => $absence->start_date->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('planned_absences', [
            'id' => $absence->id,
            'end_date' => $absence->start_date->format('Y-m-d').' 00:00:00',
        ]);
    }

    #[Test]
    public function update_updates_reason_and_notes(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['reason' => PlannedAbsenceReason::Holiday, 'notes' => 'Old notes.']);

        $response = $this->actingAs($user)->patchJson(route('raiding.absences.update', $absence), [
            'reason' => PlannedAbsenceReason::Work->value,
            'notes' => 'New notes.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('planned_absences', [
            'id' => $absence->id,
            'reason' => PlannedAbsenceReason::Work->value,
            'notes' => 'New notes.',
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
    public function update_validates_end_date_must_not_be_before_start_date(): void
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
            ->assertSessionHasErrors('character')
            ->assertSessionDoesntHaveErrors('game_version');
    }

    #[Group('validation')]
    #[Test]
    public function update_rejects_an_unreleased_game_version(): void
    {
        $unreleased = GameVersion::factory()->create(['release_date' => now()->addMonth()]);
        $main = Character::factory()->main()->forGameVersion($unreleased)->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $this->actingAs($this->officer())
            ->patch(route('raiding.absences.update', $absence), ['game_version' => $unreleased->id, 'character' => $main->id])
            ->assertSessionHasErrors(['character' => "That game version isn't available."])
            ->assertSessionDoesntHaveErrors('game_version');

        $this->assertNotSame($main->id, $absence->fresh()->character_id);
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

    #[Group('authorization')]
    #[Test]
    public function destroy_returns_403_without_delete_permission(): void
    {
        $user = $this->userWithPermissions(['view-planned-absences', 'backdate-planned-absences']);
        $absence = PlannedAbsence::factory()->withCharacter()->create(['start_date' => today()->addWeek()]);

        $response = $this->actingAs($user)->deleteJson(route('raiding.absences.destroy', $absence));

        $response->assertForbidden();
        $this->assertNotSoftDeleted($absence);
    }

    // ==================== destroy: Happy Paths ====================

    #[Test]
    public function destroy_deletes_a_started_absence_with_permission(): void
    {
        $user = $this->userWithPermissions(['view-planned-absences', 'delete-planned-absences']);
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
            'reason' => PlannedAbsenceReason::Holiday->value,
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
    public function update_clears_the_user_when_user_is_null(): void
    {
        $absence = PlannedAbsence::factory()->withCharacter()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->officer())
            ->patch(route('raiding.absences.update', $absence), ['user' => null])
            ->assertRedirectToRoute('raiding.absences.index');

        $this->assertNull($absence->fresh()->user_id);
    }

    #[Test]
    #[Group('validation')]
    public function store_rejects_a_discord_user_without_a_users_row(): void
    {
        $character = $this->mainInReleasedVersion();

        $this->actingAs($this->officer())
            ->post(route('raiding.absences.store'), [
                'game_version' => $character->game_version_id,
                'character' => $character->id,
                'user' => '999999999999999999',
                'start_date' => now()->addDay()->format('Y-m-d'),
                'reason' => PlannedAbsenceReason::Holiday->value,
            ])
            ->assertSessionHasErrors(['user' => 'Pick a Discord member and wait for them to finish loading.']);

        Saloon::assertNothingSent();
        $this->assertDatabaseCount('planned_absences', 0);
        $this->assertDatabaseMissing('users', ['id' => '999999999999999999']);
    }

    #[Test]
    #[Group('validation')]
    public function update_rejects_a_discord_user_without_a_users_row(): void
    {
        $absence = PlannedAbsence::factory()->withCharacter()->create();
        $originalUserId = $absence->user_id;

        $this->actingAs($this->officer())
            ->patch(route('raiding.absences.update', $absence), ['user' => '999999999999999999'])
            ->assertSessionHasErrors(['user' => 'Pick a Discord member and wait for them to finish loading.']);

        Saloon::assertNothingSent();
        $this->assertSame($originalUserId, $absence->fresh()->user_id);
        $this->assertDatabaseMissing('users', ['id' => '999999999999999999']);
    }

    // ==================== discord_members: Optional Prop ====================

    #[Test]
    public function create_does_not_search_discord_members_on_a_full_load(): void
    {
        $this->actingAs($this->officer())
            ->get(route('raiding.absences.create', ['member_search' => 'test']))
            ->assertInertia(fn (Assert $page) => $page->missing('discord_members'));

        Saloon::assertNotSent(SearchGuildMembersRequest::class);
    }

    #[Test]
    public function create_does_not_refresh_portraits_on_a_full_load(): void
    {
        $character = $this->mainInReleasedVersion();
        Queue::fake();

        $this->actingAs($this->officer())
            ->get(route('raiding.absences.create', ['character_portrait' => $character->id]))
            ->assertInertia(fn (Assert $page) => $page->missing('character_portrait'));

        RefreshCharacterPortrait::assertNotPushed();
    }

    #[Test]
    public function create_refreshes_and_returns_the_character_portrait_on_a_partial_reload(): void
    {
        $character = $this->mainInReleasedVersion();
        Queue::fake();

        $this->actingAs($this->officer())
            ->get(route('raiding.absences.create', ['character_portrait' => $character->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('character_portrait', fn (Assert $reload) => $reload
                    ->where('character_portrait', ['id' => $character->id, 'portrait_url' => null])));

        RefreshCharacterPortrait::assertPushed(1);
        RefreshCharacterPortrait::assertPushed(fn (RefreshCharacterPortrait $action, array $parameters): bool => $parameters[0]->is($character));
    }

    #[Test]
    public function edit_returns_the_character_portrait_on_a_partial_reload(): void
    {
        $character = $this->mainInReleasedVersion();
        $plannedAbsence = PlannedAbsence::factory()->create(['character_id' => $character->id]);
        Queue::fake();

        $this->actingAs($this->officer())
            ->get(route('raiding.absences.edit', ['plannedAbsence' => $plannedAbsence, 'character_portrait' => $character->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('character_portrait', fn (Assert $reload) => $reload
                    ->where('character_portrait.id', $character->id)));

        RefreshCharacterPortrait::assertPushed(1);
    }

    #[Test]
    #[Group('validation')]
    public function create_rejects_an_unknown_character_when_reloading_the_character_portrait(): void
    {
        Queue::fake();

        $officer = $this->officer();
        $version = $this->actingAs($officer)->get(route('raiding.absences.create'))->viewData('page')['version'];

        $this->actingAs($officer)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) $version,
                'X-Inertia-Partial-Component' => 'Raiding/Absences/Form',
                'X-Inertia-Partial-Data' => 'character_portrait',
            ])
            ->get(route('raiding.absences.create', ['character_portrait' => 999999999]))
            ->assertSessionHasErrors('character_portrait');

        RefreshCharacterPortrait::assertNotPushed();
    }

    #[Test]
    public function create_returns_discord_members_on_a_partial_reload(): void
    {
        $members = [
            ['id' => '111111111111111111', 'nickname' => 'TestNick', 'username' => 'testuser'],
            ['id' => '333333333333333333', 'nickname' => null, 'username' => 'nonick'],
        ];
        Cache::tags(['discord'])->put(SearchGuildMembers::cacheKey('829020506907869214', 1, 'test'), $members, now()->addMinutes(5));

        $this->actingAs($this->officer())
            ->get(route('raiding.absences.create', ['member_search' => 'test']))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('discord_members', fn (Assert $reload) => $reload
                    ->where('discord_members', ['status' => 'ready', 'members' => $members])
                )
            );

        Saloon::assertNothingSent();
    }

    #[Test]
    public function create_queues_a_member_search_on_a_cache_miss(): void
    {
        Queue::fake();

        $this->actingAs($this->officer())
            ->get(route('raiding.absences.create', ['member_search' => 'test']))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('discord_members', fn (Assert $reload) => $reload
                    ->where('discord_members', ['status' => 'pending', 'members' => []])
                )
            );

        Queue::assertPushedOn('high', SearchGuildMembers::class);
        Saloon::assertNothingSent();
    }

    #[Test]
    public function create_passes_the_member_limit_to_discord(): void
    {
        Queue::fake();
        $officer = $this->officer();

        $this->actingAs($officer)
            ->get(route('raiding.absences.create', ['member_search' => 'test', 'member_limit' => 5]))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('discord_members', fn (Assert $reload) => $reload
                    ->where('discord_members', ['status' => 'pending', 'members' => []])
                )
            );

        Queue::assertPushedOn('high', SearchGuildMembers::class, fn (SearchGuildMembers $job): bool => $job->query === 'test' && $job->limit === 5 && $job->requestedBy === $officer->id);
    }

    #[Test]
    public function create_queues_the_member_search_with_surrounding_whitespace_trimmed(): void
    {
        Queue::fake();
        $officer = $this->officer();

        $this->actingAs($officer)
            ->get(route('raiding.absences.create', ['member_search' => ' test ', 'member_limit' => 5]))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('discord_members', fn (Assert $reload) => $reload
                    ->where('discord_members', ['status' => 'pending', 'members' => []])
                )
            );

        Queue::assertPushedOn('high', SearchGuildMembers::class, fn (SearchGuildMembers $job): bool => $job->query === 'test' && $job->limit === 5 && $job->requestedBy === $officer->id);
    }

    #[Test]
    #[Group('validation')]
    public function create_requires_a_member_search_when_reloading_discord_members(): void
    {
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

        Saloon::assertNotSent(SearchGuildMembersRequest::class);
    }

    #[Test]
    public function create_caches_discord_member_searches(): void
    {
        // Serialize like Redis does, so cached objects come back as __PHP_Incomplete_Class under serializable_classes=false.
        config(['cache.stores.array.serialize' => true]);
        Cache::forgetDriver('array');

        Event::fake([DiscordMembersSearched::class]);
        Saloon::fake([SearchGuildMembersRequest::class => MockResponse::make([$this->makeDiscordGuildMemberWithUser('444444444444444444', 'cacheduser', 'Cached')])]);

        $officer = $this->officer();
        $expected = [
            ['status' => 'pending', 'members' => []],
            ['status' => 'ready', 'members' => [['id' => '444444444444444444', 'nickname' => 'Cached', 'username' => 'cacheduser']]],
        ];

        foreach ($expected as $lookup) {
            $this->actingAs($officer)
                ->get(route('raiding.absences.create', ['member_search' => 'cach']))
                ->assertInertia(fn (Assert $page) => $page
                    ->reloadOnly('discord_members', fn (Assert $reload) => $reload->where('discord_members', $lookup))
                );
        }

        Saloon::mockClient()->assertSentCount(1, SearchGuildMembersRequest::class);
        Event::assertDispatchedTimes(DiscordMembersSearched::class, 1);
    }

    #[Test]
    public function edit_returns_discord_members_on_a_partial_reload(): void
    {
        $absence = PlannedAbsence::factory()->withCharacter()->create();
        $members = [['id' => '222222222222222222', 'nickname' => 'Guild Officer', 'username' => 'guildofficer']];
        Cache::tags(['discord'])->put(SearchGuildMembers::cacheKey('829020506907869214', 1, 'officer'), $members, now()->addMinutes(5));

        $this->actingAs($this->officer())
            ->get(route('raiding.absences.edit', [$absence, 'member_search' => 'officer']))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('discord_members', fn (Assert $reload) => $reload
                    ->where('discord_members', ['status' => 'ready', 'members' => $members])
                )
            );

        Saloon::assertNothingSent();
    }

    // ==================== helpers ====================

    /**
     * @param  array{update: bool, delete: bool, restore: bool}  $expected
     */
    private function assertIndexPermissions(User $user, array $expected): void
    {
        $this->actingAs($user)
            ->get(route('raiding.absences.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('planned_absences.permissions', $expected)
                    ->has('past_planned_absences', 1)
                    ->missing('past_planned_absences.0.permissions')
                )
            );
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
     * A user holding exactly the given permissions through a dedicated role.
     *
     * @param  array<int, string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $role = DiscordRole::factory()->create();

        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        $user = User::factory()->create();
        $user->discordRoles()->attach($role->id);

        return $user;
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

    /**
     * An absence that ended last week.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function pastAbsence(array $attributes = []): PlannedAbsence
    {
        return PlannedAbsence::factory()->withCharacter()->create([
            'start_date' => today()->subDays(10),
            'end_date' => today()->subDays(5),
            ...$attributes,
        ]);
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
            'reason' => PlannedAbsenceReason::Holiday->value,
        ];
    }
}
