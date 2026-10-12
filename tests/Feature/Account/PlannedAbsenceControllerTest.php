<?php

namespace Tests\Feature\Account;

use App\Actions\Characters\RefreshCharacterPortrait;
use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Contracts\Http\SharesBattleNetSession;
use App\Data\PlannedAbsenceReasonData;
use App\Enums\PlannedAbsenceReason;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Middleware\EagerlyMirrorAssets;
use App\Http\Integrations\Blizzard\Region;
use App\Http\Integrations\Blizzard\Requests\Account\GetAccountProfileSummaryRequest;
use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\GameVersion;
use App\Models\Permission;
use App\Models\PlannedAbsence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Auth\AccessTokenAuthenticator;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

#[Group('raiding')]
#[Group('auth')]
#[Group('blizzard-integration')]
class PlannedAbsenceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->app->instance(BlizzardConnector::class, new BlizzardConnector(
            clientId: 'test_id',
            clientSecret: 'test_secret',
            region: Region::EU,
            locale: Region::EU->defaultLocale(),
            redirectUri: 'https://regrowth.test/auth/battlenet/callback',
            eagerlyMirrorAssets: $this->app->make(EagerlyMirrorAssets::class),
        ));

        $officerRole = DiscordRole::firstOrCreate(
            ['id' => '829021769448816691'],
            ['name' => 'Officer', 'position' => 6, 'is_visible' => true]
        );
        $raiderRole = DiscordRole::firstOrCreate(
            ['id' => '1265247017215594496'],
            ['name' => 'Raider', 'position' => 4, 'is_visible' => true]
        );

        foreach (['view-planned-absences', 'create-planned-absences', 'backdate-planned-absences', 'update-planned-absences', 'delete-planned-absences'] as $permission) {
            $officerRole->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        $raiderRole->givePermissionTo(Permission::firstOrCreate(['name' => 'create-planned-absences', 'guard_name' => 'web']));
    }

    // ==================== create ====================

    #[Test]
    #[Group('authorization')]
    public function create_returns_403_without_create_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('account.absences.create'))
            ->assertForbidden();
    }

    #[Test]
    public function create_remembers_itself_as_the_battle_net_return_url(): void
    {
        $this->actingAs($this->member())
            ->get(route('account.absences.create'))
            ->assertSessionHas(SharesBattleNetSession::INTENDED_URL_KEY, route('account.absences.create'));
    }

    #[Test]
    public function create_reports_not_connected_without_a_token(): void
    {
        GameVersion::factory()->fetchableRoster()->create();
        Saloon::fake([]);

        $this->actingAs($this->member())
            ->get(route('account.absences.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Absences/Form')
                ->where('battle_net.connected', false)
                ->where('action', route('account.absences.store'))
                ->where('reasons', PlannedAbsenceReasonData::options()->toArray())
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('account_characters', ['characters' => ['data' => []], 'mains_only' => true, 'error' => null])));

        Saloon::assertNothingSent();
    }

    #[Test]
    public function create_treats_an_expired_token_as_not_connected(): void
    {
        GameVersion::factory()->fetchableRoster()->create();

        $this->actingAs($this->member())
            ->withSession([SharesBattleNetSession::AUTHENTICATOR_KEY => new AccessTokenAuthenticator('user-token', expiresAt: now()->subMinute()->toDateTimeImmutable())])
            ->get(route('account.absences.create'))
            ->assertInertia(fn (Assert $page) => $page->where('battle_net.connected', false));
    }

    #[Test]
    #[Group('happy-path')]
    public function create_lists_the_accounts_mains_in_the_current_version(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $mine = Character::factory()->main()->forGameVersion($gameVersion)->create(['id' => 52963250, 'name' => 'Auburn']);
        Character::factory()->forGameVersion($gameVersion)->create(['id' => 79938434, 'name' => 'Noonian', 'is_main' => false]);
        Character::factory()->main()->forGameVersion($gameVersion)->create(['id' => 11111111, 'name' => 'Stranger']);
        $this->fakeAccount([52963250, 79938434]);

        $this->actingAs($this->member())
            ->withSession($this->battleNetSession())
            ->get(route('account.absences.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Absences/Form')
                ->where('game_version.data.id', $gameVersion->id)
                ->where('battle_net.connected', true)
                ->missing('account_characters')
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('account_characters.error', null)
                    ->where('account_characters.mains_only', true)
                    ->has('account_characters.characters.data', 1)
                    ->where('account_characters.characters.data.0.id', $mine->id)));
    }

    #[Test]
    public function create_refreshes_the_portraits_of_the_selectable_account_characters(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $mine = Character::factory()->main()->forGameVersion($gameVersion)->create(['id' => 52963250]);
        Character::factory()->forGameVersion($gameVersion)->create(['id' => 79938434, 'is_main' => false]);
        $this->fakeAccount([52963250, 79938434]);
        Queue::fake();

        $this->actingAs($this->member())
            ->withSession($this->battleNetSession())
            ->get(route('account.absences.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload->has('account_characters.characters.data', 1)));

        RefreshCharacterPortrait::assertPushed(1);
        RefreshCharacterPortrait::assertPushed(fn (RefreshCharacterPortrait $action, array $parameters): bool => $parameters[0]->is($mine));
    }

    #[Test]
    public function create_returns_the_character_portrait_on_a_partial_reload(): void
    {
        GameVersion::factory()->fetchableRoster()->create();
        $character = Character::factory()->create();
        Queue::fake();

        $this->actingAs($this->member())
            ->get(route('account.absences.create', ['character_portrait' => $character->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('character_portrait')
                ->reloadOnly('character_portrait', fn (Assert $reload) => $reload
                    ->where('character_portrait', ['id' => $character->id, 'portrait_url' => null])));

        RefreshCharacterPortrait::assertPushed(1);
    }

    #[Test]
    public function create_lists_the_highest_level_account_characters_when_none_is_a_main(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $auburn = Character::factory()->forGameVersion($gameVersion)->create(['id' => 52963250, 'name' => 'Auburn', 'is_main' => false, 'level' => 68]);
        Character::factory()->forGameVersion($gameVersion)->create(['id' => 61234567, 'name' => 'Lowbie', 'is_main' => false, 'level' => 30]);
        $noonian = Character::factory()->forGameVersion($gameVersion)->create(['id' => 79938434, 'name' => 'Noonian', 'is_main' => false, 'level' => 68]);
        $this->fakeAccount([52963250, 61234567, 79938434]);

        $this->actingAs($this->member())
            ->withSession($this->battleNetSession())
            ->get(route('account.absences.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('account_characters.mains_only', false)
                    ->where('account_characters.characters.data', fn ($characters) => collect($characters)->pluck('id')->all() === [$auburn->id, $noonian->id])));
    }

    #[Test]
    #[Group('error-handling')]
    public function create_asks_to_reconnect_and_forgets_a_rejected_token(): void
    {
        GameVersion::factory()->fetchableRoster()->create();
        Saloon::fake([GetAccountProfileSummaryRequest::class => MockResponse::make(['code' => 401, 'type' => 'BLZWEBAPI00000401', 'detail' => 'Unauthorized'], 401)]);

        $this->actingAs($this->member())
            ->withSession($this->battleNetSession())
            ->get(route('account.absences.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('account_characters', ['characters' => ['data' => []], 'mains_only' => true, 'error' => 'reconnect'])));

        $this->assertNull(session(SharesBattleNetSession::AUTHENTICATOR_KEY));
    }

    #[Test]
    #[Group('error-handling')]
    public function create_reports_battle_net_unavailable_on_a_server_error(): void
    {
        GameVersion::factory()->fetchableRoster()->create();
        Saloon::fake([GetAccountProfileSummaryRequest::class => MockResponse::make(['code' => 500, 'type' => 'BLZWEBAPI00000500', 'detail' => 'Internal Server Error'], 500)]);

        $this->actingAs($this->member())
            ->withSession($this->battleNetSession())
            ->get(route('account.absences.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('account_characters', ['characters' => ['data' => []], 'mains_only' => true, 'error' => 'unavailable'])));

        $this->assertNotNull(session(SharesBattleNetSession::AUTHENTICATOR_KEY));
    }

    #[Test]
    public function create_falls_back_when_the_session_version_is_not_a_current_roster(): void
    {
        $currentRoster = GameVersion::factory()->fetchableRoster()->create();
        $officerOnly = GameVersion::factory()->create(['blizzard_namespace' => null, 'release_date' => now()->subDay()]);

        $this->actingAs($this->member())
            ->withSession([ResolveCurrentGameVersion::SESSION_KEY => $officerOnly->slug])
            ->get(route('account.absences.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('game_version.data.id', $currentRoster->id)
                ->where('game_versions.data', fn ($gameVersions) => collect($gameVersions)->pluck('id')->all() === [$currentRoster->id]))
            ->assertSessionHas(ResolveCurrentGameVersion::SESSION_KEY, $officerOnly->slug);
    }

    // ==================== store ====================

    #[Test]
    #[Group('authorization')]
    public function store_returns_403_without_create_permission(): void
    {
        [$gameVersion, $character] = $this->accountMain();

        $this->actingAs(User::factory()->create())
            ->withSession($this->battleNetSession())
            ->post(route('account.absences.store'), $this->absencePayload($gameVersion, $character))
            ->assertForbidden();
    }

    #[Test]
    #[Group('happy-path')]
    public function store_creates_the_absence_for_the_signed_in_user(): void
    {
        [$gameVersion, $character] = $this->accountMain();
        $member = $this->member();

        $this->actingAs($member)
            ->withSession($this->battleNetSession())
            ->post(route('account.absences.store'), $this->absencePayload($gameVersion, $character))
            ->assertRedirectToRoute('account.index')
            ->assertSessionHas('success', 'Planned absence created successfully.');

        $this->assertDatabaseHas('planned_absences', [
            'character_id' => $character->id,
            'user_id' => $member->id,
            'created_by' => $member->id,
            'reason' => PlannedAbsenceReason::Holiday->value,
        ]);
    }

    #[Test]
    #[Group('validation')]
    public function store_rejects_an_alt_when_the_account_has_a_main(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        Character::factory()->main()->forGameVersion($gameVersion)->create(['id' => 52963250, 'name' => 'Auburn']);
        $alt = Character::factory()->forGameVersion($gameVersion)->create(['id' => 79938434, 'name' => 'Noonian', 'is_main' => false]);
        $this->fakeAccount([52963250, 79938434]);

        $this->assertStoreRejected($this->absencePayload($gameVersion, $alt), ['character' => 'Choose a main character.']);
    }

    #[Test]
    public function store_accepts_a_non_main_when_the_account_has_no_main(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $alt = Character::factory()->forGameVersion($gameVersion)->create(['id' => 79938434, 'name' => 'Noonian', 'is_main' => false]);
        $this->fakeAccount([79938434]);

        $this->actingAs($this->member())
            ->withSession($this->battleNetSession())
            ->post(route('account.absences.store'), $this->absencePayload($gameVersion, $alt))
            ->assertSessionHasNoErrors()
            ->assertRedirectToRoute('account.index');

        $this->assertDatabaseHas('planned_absences', ['character_id' => $alt->id]);
    }

    #[Test]
    #[Group('validation')]
    public function store_rejects_a_roster_character_that_is_not_on_the_account(): void
    {
        [$gameVersion] = $this->accountMain();
        $someoneElse = Character::factory()->main()->forGameVersion($gameVersion)->create(['id' => 11111111, 'name' => 'Stranger']);

        $this->assertStoreRejected($this->absencePayload($gameVersion, $someoneElse), ['character' => "That character isn't on your Battle.net account."]);
    }

    #[Test]
    #[Group('validation')]
    public function store_asks_to_reconnect_without_a_token(): void
    {
        [$gameVersion, $character] = $this->accountMain();

        $this->actingAs($this->member())
            ->post(route('account.absences.store'), $this->absencePayload($gameVersion, $character))
            ->assertSessionHasErrors(['character' => 'Your Battle.net connection has expired. Please connect again.']);

        $this->assertDatabaseCount('planned_absences', 0);
        Saloon::assertNothingSent();
    }

    #[Test]
    #[Group('validation')]
    public function store_asks_to_reconnect_when_the_token_is_rejected(): void
    {
        [$gameVersion, $character] = $this->accountMain();
        Saloon::fake([GetAccountProfileSummaryRequest::class => MockResponse::make(['code' => 401, 'type' => 'BLZWEBAPI00000401', 'detail' => 'Unauthorized'], 401)]);

        $this->assertStoreRejected($this->absencePayload($gameVersion, $character), ['character' => 'Your Battle.net connection has expired. Please connect again.']);

        $this->assertNull(session(SharesBattleNetSession::AUTHENTICATOR_KEY));
    }

    #[Test]
    #[Group('validation')]
    public function store_reports_battle_net_unavailable(): void
    {
        [$gameVersion, $character] = $this->accountMain();
        Saloon::fake([GetAccountProfileSummaryRequest::class => MockResponse::make(['code' => 500, 'type' => 'BLZWEBAPI00000500', 'detail' => 'Internal Server Error'], 500)]);

        $this->assertStoreRejected($this->absencePayload($gameVersion, $character), ['character' => 'Battle.net is unavailable right now. Please try again shortly.']);
    }

    #[Test]
    #[Group('validation')]
    public function store_rejects_a_version_that_is_not_a_current_roster(): void
    {
        GameVersion::factory()->fetchableRoster()->create();
        $officerOnly = GameVersion::factory()->create(['blizzard_namespace' => null, 'release_date' => now()->subDay()]);
        $character = Character::factory()->main()->forGameVersion($officerOnly)->create(['id' => 52963250, 'name' => 'Auburn']);
        $this->fakeAccount([52963250]);

        $this->assertStoreRejected($this->absencePayload($officerOnly, $character), ['game_version' => "That game version isn't available."]);
    }

    #[Test]
    #[Group('validation')]
    public function store_prohibits_the_user_field(): void
    {
        [$gameVersion, $character] = $this->accountMain();
        $someoneElse = User::factory()->create();

        $this->assertStoreRejected([...$this->absencePayload($gameVersion, $character), 'user' => $someoneElse->id], ['user']);
    }

    #[Test]
    #[Group('validation')]
    public function store_rejects_a_past_start_date(): void
    {
        [$gameVersion, $character] = $this->accountMain();

        $this->assertStoreRejected(
            [...$this->absencePayload($gameVersion, $character), 'start_date' => now()->subDay()->format('Y-m-d')],
            ['start_date' => 'The start date must not be in the past.'],
        );
    }

    // ==================== edit ====================

    #[Test]
    public function edit_loads_for_the_owner(): void
    {
        $member = $this->member();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['user_id' => $member->id, 'created_by' => $member->id, 'start_date' => today()->addWeek()]);
        Saloon::fake([]);

        $this->actingAs($member)
            ->get(route('account.absences.edit', $absence))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Absences/Form')
                ->where('planned_absence.data.id', $absence->id)
                ->where('planned_absence.data.character.id', $absence->character_id)
                ->where('action', route('account.absences.update', $absence))
                ->where('reasons', PlannedAbsenceReasonData::options()->toArray())
                ->missing('account_characters')
                ->missing('game_versions'));

        Saloon::assertNothingSent();
    }

    #[Test]
    #[Group('authorization')]
    public function edit_returns_403_for_another_users_absence(): void
    {
        $absence = PlannedAbsence::factory()->withCharacter()->create(['created_by' => User::factory()->create()->id]);

        $this->actingAs($this->member())
            ->get(route('account.absences.edit', $absence))
            ->assertForbidden();
    }

    #[Test]
    #[Group('authorization')]
    public function edit_returns_403_once_the_absence_has_started(): void
    {
        $member = $this->member();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['created_by' => $member->id, 'start_date' => today()->subDay()]);

        $this->actingAs($member)
            ->get(route('account.absences.edit', $absence))
            ->assertForbidden();
    }

    // ==================== update ====================

    #[Test]
    #[Group('happy-path')]
    public function update_changes_dates_reason_and_notes_for_the_owner(): void
    {
        $member = $this->member();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['created_by' => $member->id, 'start_date' => today()->addWeek(), 'reason' => PlannedAbsenceReason::Holiday, 'notes' => 'Old notes.']);
        $startDate = now()->addDays(3)->format('Y-m-d');
        $endDate = now()->addDays(5)->format('Y-m-d');

        $this->actingAs($member)
            ->patch(route('account.absences.update', $absence), ['start_date' => $startDate, 'end_date' => $endDate, 'reason' => PlannedAbsenceReason::Work->value, 'notes' => 'New notes.'])
            ->assertRedirectToRoute('account.index')
            ->assertSessionHas('success', 'Planned absence updated successfully.');

        $absence->refresh();
        $this->assertSame($startDate, $absence->start_date->format('Y-m-d'));
        $this->assertSame($endDate, $absence->end_date->format('Y-m-d'));
        $this->assertSame(PlannedAbsenceReason::Work, $absence->reason->key);
        $this->assertSame('New notes.', $absence->notes);
    }

    #[Test]
    public function update_clears_notes(): void
    {
        $member = $this->member();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['created_by' => $member->id, 'start_date' => today()->addWeek(), 'reason' => PlannedAbsenceReason::Work, 'notes' => 'Late shift.']);

        $this->actingAs($member)
            ->patch(route('account.absences.update', $absence), ['reason' => PlannedAbsenceReason::Work->value, 'notes' => ''])
            ->assertRedirectToRoute('account.index');

        $this->assertNull($absence->fresh()->notes);
    }

    #[Test]
    #[Group('validation')]
    public function update_prohibits_changing_the_character(): void
    {
        $member = $this->member();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['created_by' => $member->id, 'start_date' => today()->addWeek()]);
        $other = Character::factory()->main()->create(['name' => 'Elsewhere']);

        $this->actingAs($member)
            ->patch(route('account.absences.update', $absence), ['character' => $other->id])
            ->assertSessionHasErrors(['character']);

        $this->assertNotSame($other->id, $absence->fresh()->character_id);
    }

    #[Test]
    #[Group('authorization')]
    public function update_returns_403_for_another_users_absence(): void
    {
        $absence = PlannedAbsence::factory()->withCharacter()->create(['created_by' => User::factory()->create()->id, 'reason' => PlannedAbsenceReason::Holiday]);

        $this->actingAs($this->member())
            ->patch(route('account.absences.update', $absence), ['reason' => PlannedAbsenceReason::Work->value])
            ->assertForbidden();

        $this->assertSame(PlannedAbsenceReason::Holiday, $absence->fresh()->reason->key);
    }

    #[Test]
    #[Group('authorization')]
    public function update_returns_403_once_the_absence_has_started(): void
    {
        $member = $this->member();
        $startDate = today()->subDay();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['created_by' => $member->id, 'start_date' => $startDate]);

        $this->actingAs($member)
            ->patch(route('account.absences.update', $absence), ['start_date' => today()->addWeek()->format('Y-m-d')])
            ->assertForbidden();

        $this->assertSame($startDate->format('Y-m-d'), $absence->fresh()->start_date->format('Y-m-d'));
    }

    // ==================== destroy ====================

    #[Test]
    #[Group('happy-path')]
    public function destroy_soft_deletes_the_owners_upcoming_absence(): void
    {
        $member = $this->member();
        $absence = PlannedAbsence::factory()->withCharacter()->create([
            'user_id' => $member->id,
            'created_by' => $member->id,
            'start_date' => today()->addWeek(),
        ]);

        $this->actingAs($member)
            ->delete(route('account.absences.destroy', $absence))
            ->assertRedirect(route('account.index'))
            ->assertSessionHas('success', 'Planned absence deleted successfully.');

        $this->assertSoftDeleted($absence);
    }

    #[Test]
    public function destroy_allows_the_owner_to_delete_an_absence_starting_today(): void
    {
        $member = $this->member();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['created_by' => $member->id, 'start_date' => today()]);

        $this->actingAs($member)
            ->delete(route('account.absences.destroy', $absence))
            ->assertRedirect(route('account.index'));

        $this->assertSoftDeleted($absence);
    }

    #[Test]
    #[Group('authorization')]
    public function destroy_returns_403_once_the_absence_has_started(): void
    {
        $member = $this->member();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['created_by' => $member->id, 'start_date' => today()->subDay()]);

        $this->actingAs($member)
            ->delete(route('account.absences.destroy', $absence))
            ->assertForbidden();

        $this->assertNotSoftDeleted($absence);
    }

    #[Test]
    #[Group('authorization')]
    public function destroy_returns_403_for_another_users_absence(): void
    {
        $absence = PlannedAbsence::factory()->withCharacter()->create(['created_by' => $this->member()->id, 'start_date' => today()->addWeek()]);

        $this->actingAs($this->member())
            ->delete(route('account.absences.destroy', $absence))
            ->assertForbidden();

        $this->assertNotSoftDeleted($absence);
    }

    // ==================== helpers ====================

    /**
     * A member who can log their own absences but not anyone else's.
     */
    private function member(): User
    {
        return User::factory()->raider()->create();
    }

    /**
     * @return array<string, AccessTokenAuthenticator>
     */
    private function battleNetSession(): array
    {
        return [SharesBattleNetSession::AUTHENTICATOR_KEY => new AccessTokenAuthenticator('user-token', expiresAt: now()->addHour()->toDateTimeImmutable())];
    }

    /**
     * Fake a Battle.net account owning the given character IDs.
     *
     * @param  list<int>  $characterIds
     */
    private function fakeAccount(array $characterIds): void
    {
        Saloon::fake([GetAccountProfileSummaryRequest::class => MockResponse::make([
            '_links' => ['self' => ['href' => 'https://eu.api.blizzard.com/profile/user/wow?namespace=profile-classicann-eu']],
            'id' => 1922840,
            'wow_accounts' => [[
                'id' => 8637580,
                'characters' => collect($characterIds)->map(fn (int $id): array => $this->accountCharacter($id))->all(),
            ]],
        ])]);
    }

    /**
     * @return array<string, mixed>
     */
    private function accountCharacter(int $id): array
    {
        return [
            'character' => ['href' => "https://eu.api.blizzard.com/profile/wow/character/thunderstrike/c{$id}?namespace=profile-classicann-eu"],
            'protected_character' => ['href' => "https://eu.api.blizzard.com/profile/user/wow/protected-character/6409-{$id}?namespace=profile-classicann-eu"],
            'name' => "C{$id}",
            'id' => $id,
            'realm' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/realm/6409?namespace=dynamic-classicann-eu'],
                'name' => 'Thunderstrike',
                'id' => 6409,
                'slug' => 'thunderstrike',
            ],
            'playable_class' => ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-class/5'], 'name' => 'Priest', 'id' => 5],
            'playable_race' => ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-race/1'], 'name' => 'Human', 'id' => 1],
            'gender' => ['type' => 'FEMALE', 'name' => 'Female'],
            'faction' => ['type' => 'ALLIANCE', 'name' => 'Alliance'],
            'level' => 70,
        ];
    }

    /**
     * A current-roster version holding one main on the faked Battle.net account.
     *
     * @return array{0: GameVersion, 1: Character}
     */
    private function accountMain(): array
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $character = Character::factory()->main()->forGameVersion($gameVersion)->create(['id' => 52963250, 'name' => 'Auburn']);
        $this->fakeAccount([52963250]);

        return [$gameVersion, $character];
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

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int|string, string>  $errors
     */
    private function assertStoreRejected(array $payload, array $errors): void
    {
        $this->actingAs($this->member())
            ->withSession($this->battleNetSession())
            ->post(route('account.absences.store'), $payload)
            ->assertSessionHasErrors($errors);

        $this->assertDatabaseCount('planned_absences', 0);
    }
}
