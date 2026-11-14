<?php

namespace Tests\Feature\Api\GuildRosterManager;

use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Enums\GuildRosterManager\UploadOutcome;
use App\Models\DesktopClient;
use App\Models\DesktopClientToken;
use App\Models\DiscordRole;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\Ban;
use App\Models\GuildRosterManager\RosterEntry;
use App\Models\GuildRosterManager\SyncRow;
use App\Models\GuildRosterManager\Upload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

#[Group('characters')]
class SyncBatchControllerTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = '9d4c2f3e-6a1b-4c8e-9f0a-1b2c3d4e5f60';

    private const BAN_PERMISSIONS = ['create-grm-bans', 'update-grm-bans'];

    private User $uploader;

    private GameVersion $forever;

    private DesktopClient $desktopClient;

    private Upload $sync;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->freezeTime();

        $this->uploader = $this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters', ...self::BAN_PERMISSIONS]);
        $this->forever = GameVersion::factory()->fetchableRoster()->withWowClientFolder('_forever_')->create(['slug' => 'forever']);
        $this->desktopClient = DesktopClient::factory()->create(['id' => self::CLIENT_ID, 'user_id' => $this->uploader->id]);
        $this->sync = $this->openSync($this->uploader, $this->desktopClient);

        RateLimiter::clear(md5('grm-sync-requests'.$this->uploader->id));
        Passport::actingAs($this->uploader, ['grm:upload']);
        $this->bindToken($this->uploader, $this->desktopClient);
    }

    // ==================== happy paths ====================

    #[Test]
    #[Group('happy-path')]
    public function it_stages_a_members_batch(): void
    {
        $members = $this->fixture('members');

        $this->postGzipped($this->uri('members'), ['rows' => $members])
            ->assertOk()
            ->assertExactJson(['staged' => 3]);

        $this->assertSame(3, SyncRow::where('upload_id', $this->sync->id)->where('kind', RosterEntryKind::Member)->count());
        $row = SyncRow::where('key', $members[0]['guid'])->sole();
        $expected = $members[0];
        unset($expected['epoch']);
        $this->assertSame($expected, json_decode($row->getRawOriginal('payload'), true));
        $this->assertSame($members[0]['epoch'], $row->epoch);
    }

    #[Test]
    public function it_stages_a_leavers_batch(): void
    {
        $this->postGzipped($this->uri('leavers'), ['rows' => $this->fixture('leavers')])
            ->assertOk()
            ->assertExactJson(['staged' => 1]);
    }

    #[Test]
    public function it_stages_an_alt_groups_batch(): void
    {
        $this->postGzipped($this->uri('alt-groups'), ['rows' => $this->fixture('altGroups')])
            ->assertOk()
            ->assertExactJson(['staged' => 1]);

        $this->assertSame('12', SyncRow::sole()->key);
    }

    #[Test]
    public function it_stages_a_bans_batch_with_a_reason(): void
    {
        $this->postGzipped($this->uri('bans'), ['rows' => $this->fixture('bans')])
            ->assertOk()
            ->assertExactJson(['staged' => 1]);

        $this->assertSame($this->fixture('bans')[0]['reason'], SyncRow::sole()->payload->reason);
    }

    #[Test]
    public function it_stages_an_unbanned_ban_row(): void
    {
        $this->postGzipped($this->uri('bans'), ['rows' => [$this->banRow(['unbanned' => true, 'reason' => null])]])->assertOk();

        $this->assertTrue(SyncRow::sole()->payload->unbanned);
    }

    #[Test]
    public function it_accepts_a_member_at_level_10(): void
    {
        $this->postGzipped($this->uri('members'), ['rows' => [$this->memberRow(['level' => 10])]])->assertOk();
    }

    #[Test]
    public function it_stages_a_future_dated_epoch(): void
    {
        $this->postGzipped($this->uri('members'), ['rows' => [$this->memberRow(['epoch' => now()->addYear()->timestamp])]])->assertOk();
    }

    #[Test]
    public function it_accepts_a_75_character_multibyte_reason(): void
    {
        $this->postGzipped($this->uri('bans'), ['rows' => [$this->banRow(['reason' => str_repeat('é', 75)])]])->assertOk();

        $this->assertSame(1, SyncRow::count());
    }

    // ==================== validation ====================

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_reason_over_75_characters_with_422(): void
    {
        $this->postGzipped($this->uri('bans'), ['rows' => [$this->banRow(['reason' => str_repeat('é', 76)])]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rows.0.reason']);

        $this->assertSame(0, SyncRow::count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function excludedFields(): array
    {
        return array_combine(
            ['note', 'officerNote', 'customNote', 'birthdayInfo', 'nickInfo', 'prof1', 'prof2', 'zone', 'timeEnteredZone', 'lastOnline', 'lastOnlineTime', 'lastOnlineHours', 'trusted', 'sneaky'],
            array_map(fn (string $key): array => [$key], ['note', 'officerNote', 'customNote', 'birthdayInfo', 'nickInfo', 'prof1', 'prof2', 'zone', 'timeEnteredZone', 'lastOnline', 'lastOnlineTime', 'lastOnlineHours', 'trusted', 'sneaky']),
        );
    }

    #[Test]
    #[Group('validation')]
    #[DataProvider('excludedFields')]
    public function it_rejects_each_excluded_field_by_name(string $key): void
    {
        $value = 'distinctive-value-'.$key;

        $response = $this->postGzipped($this->uri('members'), ['rows' => [$this->memberRow([$key => $value])]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(["rows.0.{$key}"]);

        $this->assertStringNotContainsString($value, $response->getContent());
        $this->assertSame(0, SyncRow::count());
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_an_unknown_key_in_a_history_entry_with_422(): void
    {
        $row = $this->memberRow(['history' => [['type' => 'joined', 'date' => '2026-08-02', 'sneaky' => 'x']]]);

        $this->postGzipped($this->uri('members'), ['rows' => [$row]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rows.0.history.0.sneaky']);
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_verified_key_in_a_history_entry_with_422(): void
    {
        $row = $this->memberRow(['history' => [['type' => 'joined', 'date' => '2026-08-02', 'verified' => true]]]);

        $this->postGzipped($this->uri('members'), ['rows' => [$row]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rows.0.history.0.verified']);
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_an_unknown_key_on_alt_group_and_ban_rows_with_422(): void
    {
        $altGroup = [...$this->fixture('altGroups')[0], 'sneaky' => 'x'];

        $this->postGzipped($this->uri('alt-groups'), ['rows' => [$altGroup]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rows.0.sneaky']);

        $this->postGzipped($this->uri('bans'), ['rows' => [$this->banRow(['sneaky' => 'x'])]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rows.0.sneaky']);

        $this->assertSame(0, SyncRow::count());
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_member_below_level_10_with_422(): void
    {
        $rows = [$this->memberRow(), $this->memberRow(['guid' => 'Player-1-BBBB', 'level' => 9])];

        $this->postGzipped($this->uri('members'), ['rows' => $rows])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rows.1.level']);

        $this->assertSame(0, SyncRow::count());
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_leaver_below_level_10_with_422(): void
    {
        $leaver = [...$this->fixture('leavers')[0], 'level' => 9];

        $this->postGzipped($this->uri('leavers'), ['rows' => [$leaver]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rows.0.level']);

        $this->assertSame(0, SyncRow::count());
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_251_rows_with_422(): void
    {
        $rows = array_map(fn (int $i): array => $this->memberRow(['guid' => "Player-{$i}-AAAA"]), range(1, 251));

        $this->postGzipped($this->uri('members'), ['rows' => $rows])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rows']);
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_history_entry_that_is_not_an_object_with_422(): void
    {
        $this->postGzipped($this->uri('members'), ['rows' => [$this->memberRow(['history' => ['joined']])]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rows.0.history.0' => 'The history entry must be an object.']);
    }

    #[Test]
    #[Group('validation')]
    public function it_skips_the_history_event_checks_for_a_row_whose_history_is_not_an_array(): void
    {
        $response = $this->postGzipped($this->uri('members'), ['rows' => [$this->memberRow(['history' => 'joined'])]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rows.0.history']);

        $this->assertSame(['rows.0.history'], array_keys($response->json('errors')));
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_an_invalid_history_event_with_422(): void
    {
        foreach ([['type' => 'quit', 'date' => '2026-08-02'], ['type' => 'joined', 'date' => '2026-8-2'], ['type' => 'joined', 'date' => '02/08/2026']] as $event) {
            $this->postGzipped($this->uri('members'), ['rows' => [$this->memberRow(['history' => [$event]])]])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['rows.0.history.0.'.($event['type'] === 'quit' ? 'type' : 'date')]);
        }
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_banned_at_with_an_offset_with_422(): void
    {
        $this->postGzipped($this->uri('bans'), ['rows' => [$this->banRow(['bannedAt' => '2026-10-03T20:00:00+01:00'])]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rows.0.bannedAt']);
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_missing_epoch_with_422(): void
    {
        $row = $this->memberRow();
        unset($row['epoch']);

        $this->postGzipped($this->uri('members'), ['rows' => [$row]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rows.0.epoch']);
    }

    // ==================== authorization ====================

    #[Test]
    #[Group('authorization')]
    public function it_forbids_the_bans_batch_without_ban_permissions(): void
    {
        $this->actAs($this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters']), $this->sync);

        $this->postGzipped($this->uri('bans'), ['rows' => [$this->banRow()]])->assertForbidden();

        $this->assertSame(0, SyncRow::count());
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_the_bans_batch_for_a_sync_opened_by_another_desktop_client(): void
    {
        $otherClient = DesktopClient::factory()->create(['user_id' => $this->uploader->id]);
        $this->sync->forceFill(['desktop_client_id' => $otherClient->id])->save();

        $this->postGzipped($this->uri('bans'), ['rows' => [$this->banRow()]])->assertForbidden();

        $this->assertSame(0, SyncRow::count());
    }

    #[Test]
    public function it_stages_a_new_ban_for_a_user_with_only_create(): void
    {
        $this->actAs($this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters', 'create-grm-bans']), $this->sync);

        $this->postGzipped($this->uri('bans'), ['rows' => [$this->banRow()]])->assertOk();
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_a_new_ban_for_a_user_with_only_update(): void
    {
        $this->actAs($this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters', 'update-grm-bans']), $this->sync);

        $this->postGzipped($this->uri('bans'), ['rows' => [$this->banRow()]])->assertForbidden();
    }

    #[Test]
    public function it_stages_a_changed_ban_for_a_user_with_only_update(): void
    {
        $this->existingBan('Player-9-BAAD');
        $this->actAs($this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters', 'update-grm-bans']), $this->sync);

        $this->postGzipped($this->uri('bans'), ['rows' => [$this->banRow(['guid' => 'Player-9-BAAD'])]])->assertOk();
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_a_changed_ban_for_a_user_with_only_create(): void
    {
        $this->existingBan('Player-9-BAAD');
        $this->actAs($this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters', 'create-grm-bans']), $this->sync);

        $this->postGzipped($this->uri('bans'), ['rows' => [$this->banRow(['guid' => 'Player-9-BAAD'])]])->assertForbidden();

        $this->assertSame(0, SyncRow::count());
    }

    #[Test]
    public function it_stages_a_ban_with_only_a_logged_row_for_a_create_only_user(): void
    {
        Ban::factory()->create(['game_version_id' => $this->forever->id, 'grm_guid' => 'Player-9-BAAD']);
        $this->actAs($this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters', 'create-grm-bans']), $this->sync);

        $this->postGzipped($this->uri('bans'), ['rows' => [$this->banRow(['guid' => 'Player-9-BAAD'])]])->assertOk();

        $this->assertDatabaseHas('grm_sync_rows', ['upload_id' => $this->sync->id, 'kind' => RosterEntryKind::Ban->value, 'key' => 'Player-9-BAAD']);
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_a_mixed_ban_batch_for_an_update_only_user(): void
    {
        $this->existingBan('Player-9-BAAD');
        $this->actAs($this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters', 'update-grm-bans']), $this->sync);

        $this->postGzipped($this->uri('bans'), ['rows' => [
            $this->banRow(['guid' => 'Player-9-BAAD']),
            $this->banRow(['guid' => 'Player-9-F00D']),
        ]])->assertForbidden();

        $this->assertSame(0, SyncRow::count());
    }

    #[Test]
    public function it_rejects_an_uncompressed_batch_with_400_and_writes_nothing(): void
    {
        $newClientId = 'aaaaaaaa-6a1b-4c8e-9f0a-1b2c3d4e5f60';

        $this->postJson($this->uri('members'), ['rows' => [$this->memberRow()]], [
            'X-Desktop-Client-Id' => $newClientId,
            'X-Desktop-Client-Name' => 'Other PC',
        ])->assertStatus(400)->assertJson(['status' => 'gzip_required']);

        $this->assertDatabaseMissing('desktop_clients', ['id' => $newClientId]);
        $this->assertSame(0, SyncRow::count());
    }

    #[Test]
    public function it_sends_the_server_time_header_on_a_gzip_400(): void
    {
        $this->postJson($this->uri('members'), ['rows' => [$this->memberRow()]], $this->deviceHeaders())
            ->assertStatus(400)
            ->assertJson(['status' => 'gzip_required'])
            ->assertHeader('X-Server-Time', (string) now()->getTimestamp());
    }

    #[Test]
    public function it_answers_400_before_authentication(): void
    {
        $this->app['auth']->forgetGuards();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->withHeaders($this->deviceHeaders())
            ->postJson($this->uri('members'), ['rows' => [$this->memberRow()]])
            ->assertStatus(400);

        $this->assertSame([], $queries);
    }

    #[Test]
    #[Group('authorization')]
    public function it_answers_the_same_404_for_another_users_sync_in_every_state(): void
    {
        $other = $this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters']);
        $otherClient = DesktopClient::factory()->create(['user_id' => $other->id]);

        $targets = [
            $this->openSync($other, $otherClient)->id,
            Upload::factory()->committed(2)->create(['user_id' => $other->id, 'game_version_id' => $this->forever->id])->id,
            Upload::factory()->staging($otherClient)->create(['user_id' => $other->id, 'expires_at' => now()->subMinute()])->id,
            fake()->uuid(),
        ];

        $bodies = array_map(function (string $id): string {
            $response = $this->postGzipped(route('api.grm.syncs.members.store', ['sync' => $id]), ['rows' => [$this->memberRow()]]);
            $response->assertNotFound();

            return $response->getContent();
        }, $targets);

        $this->assertCount(1, array_unique($bodies));
        $this->assertSame(0, SyncRow::count());
    }

    #[Test]
    #[Group('authorization')]
    public function it_answers_404_for_a_manual_upload_id(): void
    {
        $manual = Upload::factory()->create(['user_id' => $this->uploader->id, 'game_version_id' => $this->forever->id]);

        $this->postGzipped(route('api.grm.syncs.members.store', ['sync' => $manual->id]), ['rows' => [$this->memberRow()]])
            ->assertNotFound();
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_the_owners_other_device(): void
    {
        $otherDevice = DesktopClient::factory()->create(['user_id' => $this->uploader->id]);
        $this->bindToken($this->uploader, $otherDevice);

        $this->postGzipped($this->uri('members'), ['rows' => [$this->memberRow()]], [
            'X-Desktop-Client-Id' => $otherDevice->id,
        ])->assertForbidden();

        $this->assertSame(0, SyncRow::count());
    }

    // ==================== sync state ====================

    #[Test]
    public function it_answers_409_for_a_committed_sync(): void
    {
        $this->sync->markOutcome(UploadOutcome::Received, ['revision' => 3]);

        $this->postGzipped($this->uri('members'), ['rows' => [$this->memberRow()]])
            ->assertStatus(409)
            ->assertExactJson(['status' => 'committed']);

        $this->assertSame(0, SyncRow::count());
    }

    #[Test]
    public function it_answers_410_after_expiry(): void
    {
        $this->travel(61)->minutes();

        $this->postGzipped($this->uri('members'), ['rows' => [$this->memberRow()]])
            ->assertStatus(410)
            ->assertExactJson(['status' => 'expired']);

        $this->assertSame(0, SyncRow::count());
    }

    // ==================== helpers ====================

    private function uri(string $kind): string
    {
        return route("api.grm.syncs.{$kind}.store", ['sync' => $this->sync->id]);
    }

    private function openSync(User $user, DesktopClient $desktopClient): Upload
    {
        return Upload::factory()->staging($desktopClient)->create([
            'user_id' => $user->id,
            'game_version_id' => $this->forever->id,
        ]);
    }

    private function actAs(User $user, Upload $sync): void
    {
        $sync->forceFill(['user_id' => $user->id])->save();
        $this->desktopClient->forceFill(['user_id' => $user->id])->save();
        Passport::actingAs($user, ['grm:upload']);
        $this->bindToken($user, $this->desktopClient);
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

    private function existingBan(string $guid): void
    {
        RosterEntry::factory()->create([
            'game_version_id' => $this->forever->id,
            'kind' => RosterEntryKind::Ban,
            'key' => $guid,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fixture(string $group): array
    {
        return json_decode(file_get_contents(base_path('tests/Fixtures/GuildRosterManager/forever-roster.json')), true)[$group];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function memberRow(array $overrides = []): array
    {
        return [...$this->fixture('members')[0], ...$overrides];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function banRow(array $overrides = []): array
    {
        return [...$this->fixture('bans')[0], ...$overrides];
    }

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
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     */
    private function postGzipped(string $uri, array $body, array $headers = []): TestResponse
    {
        $server = $this->transformHeadersToServerVars([
            ...$this->deviceHeaders(),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Content-Encoding' => 'gzip',
            ...$headers,
        ]);

        return $this->call('POST', $uri, server: $server, content: gzencode(json_encode($body)));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        auth()->shouldUse('web');
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
}
