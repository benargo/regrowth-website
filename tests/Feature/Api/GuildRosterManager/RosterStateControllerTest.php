<?php

namespace Tests\Feature\Api\GuildRosterManager;

use App\Actions\GuildRosterManager\VerifyOpenTicket;
use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Models\DesktopClient;
use App\Models\DesktopClientToken;
use App\Models\DiscordRole;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\RosterEntry;
use App\Models\GuildRosterManager\RosterRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

#[Group('characters')]
class RosterStateControllerTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = '9d4c2f3e-6a1b-4c8e-9f0a-1b2c3d4e5f60';

    // ==================== state ====================

    #[Test]
    public function it_returns_the_state_with_an_etag(): void
    {
        $this->freezeTime();
        $gameVersion = $this->forever();
        $this->revision($gameVersion, 1);
        $this->revision($gameVersion, 2, ['committed_at' => now()->subMinute(), 'ranks_fingerprint' => 'ranks-fp']);
        $this->entry($gameVersion, RosterEntryKind::Member, 'Player-1-A', 'fp-m');
        $this->entry($gameVersion, RosterEntryKind::Leaver, 'Player-2-B', 'fp-l');
        $this->entry($gameVersion, RosterEntryKind::AltGroup, '7', 'fp-g');
        $this->entry($gameVersion, RosterEntryKind::Ban, 'Player-3-C', 'fp-b');

        $user = $this->bansUser();

        Passport::actingAs($user, ['grm:upload']);

        $this->bindToken($user, DesktopClient::factory()->for($user)->create(['id' => self::CLIENT_ID]));

        $response = $this->getJson(route('api.grm.rosters.state', $gameVersion), $this->deviceHeaders())
            ->assertOk()
            ->assertJson(['data' => [
                'id' => 'forever',
                'type' => 'roster_states',
                'attributes' => [
                    'revision' => 2,
                    'committedAt' => now()->subMinute()->utc()->format('Y-m-d\TH:i:s\Z'),
                    'fingerprints' => [
                        'members' => ['Player-1-A' => 'fp-m'],
                        'leavers' => ['Player-2-B' => 'fp-l'],
                        'altGroups' => ['7' => 'fp-g'],
                        'bans' => ['Player-3-C' => 'fp-b'],
                        'ranks' => 'ranks-fp',
                    ],
                    'banAccess' => ['create' => true, 'update' => true],
                ],
            ]])
            ->assertHeader('Content-Type', 'application/vnd.api+json')
            ->assertHeader('ETag', '"forever-r2-b11"')
            ->assertHeader('Cache-Control', 'no-cache, private');

        $this->assertArrayNotHasKey('capturedAt', $response->json('data.attributes'));
    }

    #[Test]
    public function it_serialises_empty_fingerprint_maps_as_objects(): void
    {
        $gameVersion = $this->forever();
        $user = $this->bansUser();
        Passport::actingAs($user, ['grm:upload']);
        $this->bindToken($user, DesktopClient::factory()->for($user)->create(['id' => self::CLIENT_ID]));

        $content = $this->getJson(route('api.grm.rosters.state', $gameVersion), $this->deviceHeaders())
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('"members":{}', $content);
        $this->assertStringContainsString('"bans":{}', $content);
    }

    #[Test]
    public function it_returns_only_the_requested_game_versions_fingerprints(): void
    {
        $gameVersion = $this->forever();
        $this->entry($gameVersion, RosterEntryKind::Member, 'Player-1-A', 'mine');
        $this->entry(GameVersion::factory()->create(), RosterEntryKind::Member, 'Player-9-Z', 'theirs');
        $user = $this->uploader();
        Passport::actingAs($user, ['grm:upload']);
        $this->bindToken($user, DesktopClient::factory()->for($user)->create(['id' => self::CLIENT_ID]));

        $this->getJson(route('api.grm.rosters.state', $gameVersion), $this->deviceHeaders())
            ->assertOk()
            ->assertJsonPath('data.attributes.fingerprints.members', ['Player-1-A' => 'mine']);
    }

    #[Test]
    public function it_returns_304_when_the_etag_matches(): void
    {
        $gameVersion = $this->forever();
        $this->revision($gameVersion, 2);
        $user = $this->uploader();
        Passport::actingAs($user, ['grm:upload']);
        $this->bindToken($user, DesktopClient::factory()->for($user)->create(['id' => self::CLIENT_ID]));

        $response = $this->getJson(
            route('api.grm.rosters.state', $gameVersion),
            [...$this->deviceHeaders(), 'If-None-Match' => '"forever-r2-b00"'],
        );

        $response->assertStatus(304)->assertHeader('ETag', '"forever-r2-b00"');
        $this->assertSame('', $response->getContent());
    }

    #[Test]
    public function it_returns_304_for_a_weak_etag(): void
    {
        $gameVersion = $this->forever();
        $this->revision($gameVersion, 2);
        $user = $this->uploader();
        Passport::actingAs($user, ['grm:upload']);
        $this->bindToken($user, DesktopClient::factory()->for($user)->create(['id' => self::CLIENT_ID]));

        $this->getJson(
            route('api.grm.rosters.state', $gameVersion),
            [...$this->deviceHeaders(), 'If-None-Match' => 'W/"forever-r2-b00"'],
        )->assertStatus(304)->assertHeader('ETag', '"forever-r2-b00"');
    }

    #[Test]
    public function it_returns_a_new_etag_after_a_commit(): void
    {
        $gameVersion = $this->forever();
        $this->revision($gameVersion, 1);
        $this->revision($gameVersion, 2);
        $user = $this->uploader();
        Passport::actingAs($user, ['grm:upload']);
        $this->bindToken($user, DesktopClient::factory()->for($user)->create(['id' => self::CLIENT_ID]));

        $this->getJson(
            route('api.grm.rosters.state', $gameVersion),
            [...$this->deviceHeaders(), 'If-None-Match' => '"forever-r1-b00"'],
        )->assertOk()->assertHeader('ETag', '"forever-r2-b00"');
    }

    // ==================== ban fingerprints ====================

    #[Test]
    public function it_omits_bans_for_a_user_without_ban_permissions(): void
    {
        $gameVersion = $this->forever();
        $this->revision($gameVersion, 2);
        $this->entry($gameVersion, RosterEntryKind::Ban, 'Player-3-C', 'fp-b');
        $user = $this->uploader();
        Passport::actingAs($user, ['grm:upload']);
        $this->bindToken($user, DesktopClient::factory()->for($user)->create(['id' => self::CLIENT_ID]));

        $response = $this->getJson(route('api.grm.rosters.state', $gameVersion), $this->deviceHeaders())
            ->assertOk()
            ->assertJson(['data' => ['attributes' => ['banAccess' => ['create' => false, 'update' => false]]]])
            ->assertHeader('ETag', '"forever-r2-b00"');

        $this->assertArrayNotHasKey('bans', $response->json('data.attributes.fingerprints'));
    }

    #[Test]
    public function it_includes_ban_fingerprints_with_only_one_ban_permission(): void
    {
        $gameVersion = $this->forever();
        $this->revision($gameVersion, 2);
        $this->entry($gameVersion, RosterEntryKind::Ban, 'Player-3-C', 'fp-b');
        $user = $this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters', 'create-grm-bans']);
        Passport::actingAs($user, ['grm:upload']);
        $this->bindToken($user, DesktopClient::factory()->for($user)->create(['id' => self::CLIENT_ID]));

        $this->getJson(route('api.grm.rosters.state', $gameVersion), $this->deviceHeaders())
            ->assertOk()
            ->assertJson(['data' => ['attributes' => [
                'fingerprints' => ['bans' => ['Player-3-C' => 'fp-b']],
                'banAccess' => ['create' => true, 'update' => false],
            ]]]);
    }

    #[Test]
    public function it_changes_the_etag_when_ban_permissions_change(): void
    {
        $gameVersion = $this->forever();
        $this->revision($gameVersion, 2);
        $user = $this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters', 'update-grm-bans']);
        Passport::actingAs($user, ['grm:upload']);
        $this->bindToken($user, DesktopClient::factory()->for($user)->create(['id' => self::CLIENT_ID]));

        $this->getJson(
            route('api.grm.rosters.state', $gameVersion),
            [...$this->deviceHeaders(), 'If-None-Match' => '"forever-r2-b00"'],
        )->assertOk()->assertHeader('ETag', '"forever-r2-b01"');
    }

    // ==================== target and token checks ====================

    #[Test]
    public function it_returns_404_for_a_version_without_a_client_folder(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create(['slug' => 'forever']);
        $user = $this->uploader();
        Passport::actingAs($user, ['grm:upload']);
        $this->bindToken($user, DesktopClient::factory()->for($user)->create(['id' => self::CLIENT_ID]));

        $this->getJson(route('api.grm.rosters.state', $gameVersion), $this->deviceHeaders())->assertNotFound();
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_a_token_without_the_upload_scope(): void
    {
        $gameVersion = $this->forever();
        $user = $this->uploader();
        Passport::actingAs($user, ['grm:targets']);
        $this->bindToken($user, DesktopClient::factory()->for($user)->create(['id' => self::CLIENT_ID]));

        $this->getJson(route('api.grm.rosters.state', $gameVersion), $this->deviceHeaders())->assertForbidden();
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_a_token_whose_user_lost_upload_grm_rosters(): void
    {
        $gameVersion = $this->forever();
        $user = $this->userWithPermissions(['use-desktop-client']);
        Passport::actingAs($user, ['grm:upload']);
        $this->bindToken($user, DesktopClient::factory()->for($user)->create(['id' => self::CLIENT_ID]));

        $this->getJson(route('api.grm.rosters.state', $gameVersion), $this->deviceHeaders())->assertForbidden();
    }

    // ==================== open ticket ====================

    #[Test]
    public function it_sends_an_open_ticket_with_the_state(): void
    {
        $gameVersion = $this->forever();
        $this->revision($gameVersion, 2);
        $user = $this->uploader();
        Passport::actingAs($user, ['grm:upload']);
        $this->bindToken($user, DesktopClient::factory()->for($user)->create(['id' => self::CLIENT_ID]));

        $ticket = $this->getJson(route('api.grm.rosters.state', $gameVersion), $this->deviceHeaders())
            ->assertOk()
            ->headers->get('X-GRM-Open-Ticket');

        $this->assertNotNull(VerifyOpenTicket::run($ticket, $user, $gameVersion, 2));
    }

    #[Test]
    public function it_sends_an_open_ticket_with_a_304(): void
    {
        $gameVersion = $this->forever();
        $this->revision($gameVersion, 2);
        $user = $this->uploader();
        Passport::actingAs($user, ['grm:upload']);
        $this->bindToken($user, DesktopClient::factory()->for($user)->create(['id' => self::CLIENT_ID]));

        $ticket = $this->getJson(
            route('api.grm.rosters.state', $gameVersion),
            [...$this->deviceHeaders(), 'If-None-Match' => '"forever-r2-b00"'],
        )->assertStatus(304)->headers->get('X-GRM-Open-Ticket');

        $this->assertNotNull(VerifyOpenTicket::run($ticket, $user, $gameVersion, 2));
    }

    // ==================== server time header ====================

    #[Test]
    public function it_sends_the_server_time_header(): void
    {
        $this->freezeTime();
        $gameVersion = $this->forever();
        $user = $this->uploader();
        Passport::actingAs($user, ['grm:upload']);
        $this->bindToken($user, DesktopClient::factory()->for($user)->create(['id' => self::CLIENT_ID]));

        $this->getJson(route('api.grm.rosters.state', $gameVersion), $this->deviceHeaders())
            ->assertHeader('X-Server-Time', (string) now()->getTimestamp());
    }

    #[Test]
    #[Group('authorization')]
    public function it_sends_the_server_time_header_on_a_403(): void
    {
        $this->freezeTime();
        $gameVersion = $this->forever();
        $user = $this->uploader();
        Passport::actingAs($user, ['grm:targets']);
        $this->bindToken($user, DesktopClient::factory()->for($user)->create(['id' => self::CLIENT_ID]));

        $this->getJson(route('api.grm.rosters.state', $gameVersion), $this->deviceHeaders())
            ->assertForbidden()
            ->assertHeader('X-Server-Time', (string) now()->getTimestamp());
    }

    #[Test]
    #[Group('authorization')]
    public function it_sends_the_server_time_header_on_a_401(): void
    {
        $this->freezeTime();
        $gameVersion = $this->forever();

        $this->getJson(route('api.grm.rosters.state', $gameVersion), $this->deviceHeaders())
            ->assertUnauthorized()
            ->assertHeader('X-Server-Time', (string) now()->getTimestamp());
    }

    // ==================== helpers ====================

    /**
     * @return array<string, string>
     */
    private function deviceHeaders(): array
    {
        return [
            'X-Desktop-Client-Id' => self::CLIENT_ID,
            'X-Desktop-Client-Name' => 'Test PC',
        ];
    }

    /**
     * Bind a new access token of the user to the desktop client and make it the user's current token.
     */
    private function bindToken(User $user, DesktopClient $desktopClient): void
    {
        $binding = DesktopClientToken::factory()->for($desktopClient)->create();

        $user->withAccessToken(new AccessToken([
            ...$user->currentAccessToken()->toArray(),
            'oauth_access_token_id' => $binding->access_token_id,
        ]));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $role = DiscordRole::factory()->grantsPermissions()->create();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $role->givePermissionTo($permission);
        }

        $user = User::factory()->create();
        $user->discordRoles()->attach($role->id);
        $user->load('discordRoles.permissions');

        return $user;
    }

    private function uploader(): User
    {
        return $this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters']);
    }

    private function forever(): GameVersion
    {
        return GameVersion::factory()->fetchableRoster()->withWowClientFolder('_forever_')->create(['slug' => 'forever']);
    }

    private function revision(GameVersion $gameVersion, int $revision, array $attributes = []): RosterRevision
    {
        return RosterRevision::factory()->create([
            'game_version_id' => $gameVersion->id,
            'revision' => $revision,
            ...$attributes,
        ]);
    }

    private function entry(GameVersion $gameVersion, RosterEntryKind $kind, string $key, string $fingerprint): RosterEntry
    {
        return RosterEntry::factory()->create([
            'game_version_id' => $gameVersion->id,
            'kind' => $kind,
            'key' => $key,
            'fingerprint' => $fingerprint,
        ]);
    }

    private function bansUser(): User
    {
        return $this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters', 'create-grm-bans', 'update-grm-bans']);
    }
}
