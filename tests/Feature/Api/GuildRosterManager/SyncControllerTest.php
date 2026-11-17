<?php

namespace Tests\Feature\Api\GuildRosterManager;

use App\Actions\GuildRosterManager\IssueOpenTicket;
use App\Data\GuildRosterManager\MemberData;
use App\Enums\GuildRosterManager\UploadOutcome;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Jobs\RefreshGuildRosterAfterEditing;
use App\Models\DesktopClient;
use App\Models\DesktopClientToken;
use App\Models\DiscordRole;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\RosterRevision;
use App\Models\GuildRosterManager\SyncRow;
use App\Models\GuildRosterManager\Upload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

#[Group('characters')]
class SyncControllerTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = '9d4c2f3e-6a1b-4c8e-9f0a-1b2c3d4e5f60';

    private User $uploader;

    private DesktopClient $desktopClient;

    private GameVersion $forever;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->freezeTime();

        $this->uploader = $this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters']);
        $this->desktopClient = DesktopClient::factory()->create(['id' => self::CLIENT_ID, 'user_id' => $this->uploader->id]);
        $this->forever = GameVersion::factory()->fetchableRoster()->withWowClientFolder('_forever_')->create([
            'slug' => 'forever',
            'guild_name' => 'Regrowth',
            'blizzard_namespace' => BlizzardNamespace::FOREVER,
        ]);

        RateLimiter::clear(md5('grm-syncs'.$this->uploader->id));
        Bus::fake([RefreshGuildRosterAfterEditing::class]);
    }

    // ==================== store ====================

    #[Test]
    #[Group('happy-path')]
    public function it_opens_a_sync_with_201(): void
    {
        $this->actingAsUploader();

        $response = $this->postGzipped(route('api.grm.syncs.store'), $this->openBody())->assertCreated();

        $upload = Upload::sole();
        $response->assertExactJson([
            'syncId' => $upload->id,
            'expiresAt' => now()->addHour()->utc()->format('Y-m-d\TH:i:s\Z'),
            'ticketExpiresAt' => now()->addMinutes(5)->utc()->format('Y-m-d\TH:i:s\Z'),
        ]);
        $this->assertSame(UploadOutcome::Staging, $upload->outcome);
        $this->assertSame(self::CLIENT_ID, $upload->desktop_client_id);
    }

    #[Test]
    public function it_returns_duplicate_with_200(): void
    {
        Upload::factory()->committed(3)->create([
            'game_version_id' => $this->forever->id,
            'idempotency_key' => $this->fileHash(),
        ]);
        $this->actingAsUploader();

        $this->postGzipped(route('api.grm.syncs.store'), $this->openBody())
            ->assertOk()
            ->assertExactJson(['duplicate' => true, 'revision' => 3]);

        $this->assertSame(1, Upload::count());
    }

    #[Test]
    public function it_returns_409_ticket_expired(): void
    {
        $this->revision(2);
        $this->actingAsUploader();

        $this->postGzipped(route('api.grm.syncs.store'), $this->openBody(['baseRevision' => 2, 'openTicket' => null]))
            ->assertConflict()
            ->assertExactJson(['status' => 'ticket_expired', 'revision' => 2]);

        $this->assertSame(0, Upload::count());
    }

    #[Test]
    public function it_returns_409_revision_conflict(): void
    {
        $this->revision(2);
        $this->actingAsUploader();

        $this->postGzipped(route('api.grm.syncs.store'), $this->openBody(['baseRevision' => 1]))
            ->assertConflict()
            ->assertExactJson(['status' => 'revision_conflict', 'revision' => 2]);

        $this->assertSame(0, Upload::count());
    }

    #[Test]
    #[Group('authorization')]
    public function it_answers_401_to_an_unauthenticated_uncompressed_open(): void
    {
        $this->app['auth']->forgetGuards();

        $this->withHeaders($this->deviceHeaders())
            ->postJson(route('api.grm.syncs.store'), ['schema' => 2])
            ->assertUnauthorized();
    }

    #[Test]
    public function it_rate_limits_after_24_opens_an_hour_per_user(): void
    {
        $desktopClients = [
            $this->desktopClient,
            DesktopClient::factory()->create(['id' => '1f2e3d4c-5b6a-4789-8abc-def012345678', 'user_id' => $this->uploader->id]),
        ];
        Passport::actingAs($this->uploader, ['grm:upload']);

        for ($attempt = 1; $attempt <= 24; $attempt++) {
            $this->bindToken($this->uploader, $desktopClients[$attempt % 2]);

            $this->postGzipped(
                route('api.grm.syncs.store'),
                $this->openBody(['fileHash' => hash('sha256', "file {$attempt}")]),
                ['X-Desktop-Client-Id' => $desktopClients[$attempt % 2]->id],
            )->assertCreated();
        }

        $this->bindToken($this->uploader, $desktopClients[1]);

        $this->postGzipped(
            route('api.grm.syncs.store'),
            $this->openBody(['fileHash' => hash('sha256', 'file 25')]),
            ['X-Desktop-Client-Id' => $desktopClients[1]->id],
        )
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_a_token_without_the_upload_scope(): void
    {
        Passport::actingAs($this->uploader, ['grm:targets']);
        $this->bindToken($this->uploader, $this->desktopClient);

        $this->postGzipped(route('api.grm.syncs.store'), $this->openBody())->assertForbidden();

        $this->assertSame(0, Upload::count());
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_a_token_whose_user_lost_upload_grm_rosters(): void
    {
        $user = $this->userWithPermissions(['use-desktop-client']);
        $desktopClient = DesktopClient::factory()->create(['user_id' => $user->id]);
        Passport::actingAs($user, ['grm:upload']);
        $this->bindToken($user, $desktopClient);

        $this->postGzipped(route('api.grm.syncs.store'), $this->openBody([], $user), ['X-Desktop-Client-Id' => $desktopClient->id])->assertForbidden();

        $this->assertSame(0, Upload::count());
    }

    // ==================== store — validation ====================

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_wrong_guild_key_with_422(): void
    {
        $this->actingAsUploader();

        $this->postGzipped(route('api.grm.syncs.store'), $this->openBody(['guildKey' => 'Another Guild']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['guildKey' => "The guildKey does not match this game version's guild."]);
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_capture_time_clock_reading_or_evidence_key_with_422(): void
    {
        $this->actingAsUploader();

        $this->postGzipped(route('api.grm.syncs.store'), $this->openBody([
            'capturedAt' => 'captured-at-value',
            'clientTime' => 'client-time-value',
            'evidence' => ['evidence-value'],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['capturedAt', 'clientTime', 'evidence.0'])
            ->assertDontSee('captured-at-value')
            ->assertDontSee('client-time-value')
            ->assertDontSee('evidence-value');
    }

    #[Test]
    #[Group('validation')]
    public function it_logs_a_refused_open_without_values(): void
    {
        Log::spy();
        $this->actingAsUploader();

        $this->postGzipped(route('api.grm.syncs.store'), $this->openBody([
            'guildKey' => 'guild-key-value',
            'capturedAt' => 'captured-at-value',
            'clientTime' => 'client-time-value',
        ]))->assertUnprocessable();

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                $this->assertSame('Desktop GRM sync open failed validation.', $message);
                $this->assertSame('1.2.3', $context['addonVersion']);
                $this->assertSame('forever', $context['gameVersion']);
                $this->assertEqualsCanonicalizing(['guildKey', 'capturedAt', 'clientTime'], $context['errors']);

                foreach (['guild-key-value', 'captured-at-value', 'client-time-value'] as $value) {
                    $this->assertStringNotContainsString($value, json_encode($context));
                }

                return true;
            });
    }

    // ==================== commit ====================

    #[Test]
    #[Group('happy-path')]
    public function it_commits_with_200_and_the_new_revision(): void
    {
        $sync = $this->stagingSync($this->uploader);
        SyncRow::factory()->create([
            'upload_id' => $sync->id,
            'key' => 'Player-4812-0A1B2C3D',
            'payload' => MemberData::from([
                'guid' => 'Player-4812-0A1B2C3D',
                'name' => 'Aelindra',
                'rankIndex' => 1,
                'level' => 70,
                'class' => 'Druid',
                'race' => 'Night Elf',
                'altGroup' => null,
                'history' => [],
            ]),
            'epoch' => 1700000000,
        ]);
        $this->actingAsUploader();

        $this->postGzipped($this->commitUri($sync), ['ranks' => ['Guild Master', 'Officer']])
            ->assertOk()
            ->assertExactJson([
                'status' => 'committed',
                'syncId' => $sync->id,
                'revision' => 1,
                'committedAt' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            ]);

        $this->assertSame(1, RosterRevision::where('game_version_id', $this->forever->id)->count());
    }

    #[Test]
    public function it_commits_an_empty_sync_with_200_and_the_current_revision(): void
    {
        $this->revision(1);
        $sync = $this->stagingSync($this->uploader, 1);
        $this->actingAsUploader();

        $this->postGzipped($this->commitUri($sync), [])
            ->assertOk()
            ->assertExactJson([
                'status' => 'committed',
                'syncId' => $sync->id,
                'revision' => 1,
                'committedAt' => null,
            ]);

        $this->assertSame(1, RosterRevision::count());
    }

    #[Test]
    public function it_replays_a_repeated_commit_with_200(): void
    {
        $sync = $this->stagingSync($this->uploader);
        $this->actingAsUploader();
        $body = ['ranks' => ['Guild Master']];

        $first = $this->postGzipped($this->commitUri($sync), $body)->assertOk();
        $second = $this->postGzipped($this->commitUri($sync), $body)->assertOk();

        $this->assertSame($first->json(), $second->json());
        $this->assertSame(1, RosterRevision::count());
    }

    #[Test]
    public function it_returns_409_to_the_second_of_two_officers(): void
    {
        $officer = $this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters']);
        $first = $this->stagingSync($this->uploader);
        $second = $this->stagingSync($officer, 0, '1f2e3d4c-5b6a-4789-8abc-def012345678');
        $this->actingAsUploader();
        $this->postGzipped($this->commitUri($first), ['ranks' => ['Guild Master']])->assertOk();

        RateLimiter::clear(md5('grm-sync-requests'.$officer->id));
        Passport::actingAs($officer, ['grm:upload']);
        $this->bindToken($officer, $second->device);

        $this->postGzipped($this->commitUri($second), ['ranks' => ['Guild Master']], ['X-Desktop-Client-Id' => '1f2e3d4c-5b6a-4789-8abc-def012345678'])
            ->assertConflict()
            ->assertExactJson(['status' => 'revision_conflict', 'revision' => 1]);
    }

    #[Test]
    public function it_returns_409_ticket_expired_for_a_lapsed_ticket(): void
    {
        $this->revision(1);
        $sync = $this->stagingSync($this->uploader, 1);
        $this->travel(301)->seconds();
        $this->actingAsUploader();

        $this->postGzipped($this->commitUri($sync), [])
            ->assertConflict()
            ->assertExactJson(['status' => 'ticket_expired', 'revision' => 1]);
    }

    #[Test]
    public function it_returns_410_for_an_expired_sync(): void
    {
        $sync = $this->stagingSync($this->uploader);
        $this->travel(2)->hours();
        $this->actingAsUploader();

        $this->postGzipped($this->commitUri($sync), ['ranks' => ['Guild Master']])
            ->assertStatus(410)
            ->assertExactJson(['status' => 'expired']);
    }

    #[Test]
    public function it_rejects_an_uncompressed_commit_with_400(): void
    {
        $sync = $this->stagingSync($this->uploader);
        $this->actingAsUploader();

        $this->postJson($this->commitUri($sync), [], $this->deviceHeaders())
            ->assertStatus(400)
            ->assertJson(['status' => 'gzip_required']);

        $this->assertSame(UploadOutcome::Staging, $sync->refresh()->outcome);
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_an_unknown_commit_key_with_422(): void
    {
        $sync = $this->stagingSync($this->uploader);
        $this->actingAsUploader();

        $this->postGzipped($this->commitUri($sync), [
            'ranks' => ['Guild Master'],
            'capturedAt' => 'captured-at-value',
            'removed' => ['ranks' => ['rank-value']],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['capturedAt', 'removed.ranks.0'])
            ->assertDontSee('captured-at-value')
            ->assertDontSee('rank-value');

        $this->assertSame(UploadOutcome::Staging, $sync->refresh()->outcome);
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_blank_rank_name_with_422(): void
    {
        $sync = $this->stagingSync($this->uploader);
        $this->actingAsUploader();

        $this->postGzipped($this->commitUri($sync), ['ranks' => ['Guild Master', '   ']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ranks.1']);

        $this->assertSame(UploadOutcome::Staging, $sync->refresh()->outcome);
    }

    #[Test]
    #[Group('authorization')]
    public function it_answers_404_for_committing_another_users_sync(): void
    {
        $other = $this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters']);
        $sync = $this->stagingSync($other, 0, '1f2e3d4c-5b6a-4789-8abc-def012345678');
        $this->actingAsUploader();

        $this->postGzipped($this->commitUri($sync), ['ranks' => ['Guild Master']])->assertNotFound();

        $this->assertSame(UploadOutcome::Staging, $sync->refresh()->outcome);
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_committing_a_sync_opened_by_another_desktop_client(): void
    {
        $otherClient = DesktopClient::factory()->create(['user_id' => $this->uploader->id]);
        $sync = $this->stagingSync($this->uploader, 0, $otherClient->id);
        $this->actingAsUploader();

        $this->postGzipped($this->commitUri($sync), ['ranks' => ['Guild Master']])->assertForbidden();

        $this->assertSame(UploadOutcome::Staging, $sync->refresh()->outcome);
        $this->assertSame(0, RosterRevision::count());
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_removed_bans_without_update_grm_bans(): void
    {
        $sync = $this->stagingSync($this->uploader);
        $this->actingAsUploader();

        $this->postGzipped($this->commitUri($sync), [
            'ranks' => ['Guild Master'],
            'removed' => ['bans' => ['Player-4812-0A1B2C3D']],
        ])->assertForbidden();

        $this->assertSame(UploadOutcome::Staging, $sync->refresh()->outcome);
        $this->assertSame(0, RosterRevision::count());
    }

    // ==================== extend ====================

    #[Test]
    public function it_extends_an_open_sync_with_200(): void
    {
        $sync = $this->stagingSync($this->uploader);
        $this->travel(4)->minutes();
        $this->actingAsUploader();

        $this->postJson($this->extendUri($sync), [], $this->deviceHeaders())
            ->assertOk()
            ->assertExactJson(['ticketExpiresAt' => now()->addMinutes(5)->utc()->format('Y-m-d\TH:i:s\Z')]);
    }

    #[Test]
    public function it_refuses_to_extend_a_committed_sync_with_409(): void
    {
        $sync = $this->stagingSync($this->uploader);
        $sync->markOutcome(UploadOutcome::Received, ['revision' => 1, 'expires_at' => null, 'ticket_expires_at' => null]);
        $this->actingAsUploader();

        $this->postJson($this->extendUri($sync), [], $this->deviceHeaders())
            ->assertConflict()
            ->assertExactJson(['status' => 'committed']);
    }

    #[Test]
    public function it_refuses_to_extend_an_abandoned_or_expired_sync_with_410(): void
    {
        $abandoned = $this->stagingSync($this->uploader);
        $abandoned->markOutcome(UploadOutcome::Abandoned, ['expires_at' => null, 'ticket_expires_at' => null]);
        $this->actingAsUploader();

        $this->postJson($this->extendUri($abandoned), [], $this->deviceHeaders())
            ->assertStatus(410)
            ->assertExactJson(['status' => 'abandoned']);

        $expired = $this->stagingSync($this->uploader, 0, '1f2e3d4c-5b6a-4789-8abc-def012345678');
        $this->travel(2)->hours();

        $this->postJson($this->extendUri($expired), [], $this->deviceHeaders())
            ->assertStatus(410)
            ->assertExactJson(['status' => 'expired']);
    }

    #[Test]
    #[Group('authorization')]
    public function it_answers_404_for_extending_another_users_sync(): void
    {
        $other = $this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters']);
        $sync = $this->stagingSync($other, 0, '1f2e3d4c-5b6a-4789-8abc-def012345678');
        $this->actingAsUploader();

        $this->postJson($this->extendUri($sync), [], $this->deviceHeaders())->assertNotFound();
    }

    // ==================== destroy ====================

    #[Test]
    public function it_abandons_a_staging_sync_with_204(): void
    {
        $sync = $this->stagingSync($this->uploader);
        SyncRow::factory()->create(['upload_id' => $sync->id]);
        $this->actingAsUploader();

        $this->deleteJson($this->syncUri($sync), [], $this->deviceHeaders())->assertNoContent();

        $sync->refresh();
        $this->assertSame(UploadOutcome::Abandoned, $sync->outcome);
        $this->assertNull($sync->expires_at);
        $this->assertNull($sync->ticket_expires_at);
        $this->assertSame(0, SyncRow::where('upload_id', $sync->id)->count());
    }

    #[Test]
    public function it_is_idempotent_for_an_abandoned_sync(): void
    {
        $sync = $this->stagingSync($this->uploader);
        $this->actingAsUploader();

        $this->deleteJson($this->syncUri($sync), [], $this->deviceHeaders())->assertNoContent();
        $this->deleteJson($this->syncUri($sync), [], $this->deviceHeaders())->assertNoContent();

        $this->assertSame(UploadOutcome::Abandoned, $sync->refresh()->outcome);
    }

    #[Test]
    public function it_returns_409_for_a_committed_sync(): void
    {
        $sync = $this->stagingSync($this->uploader);
        $sync->markOutcome(UploadOutcome::Received, ['revision' => 1, 'expires_at' => null, 'ticket_expires_at' => null]);
        $this->actingAsUploader();

        $this->deleteJson($this->syncUri($sync), [], $this->deviceHeaders())
            ->assertConflict()
            ->assertExactJson(['status' => 'committed']);

        $this->assertSame(UploadOutcome::Received, $sync->refresh()->outcome);
    }

    #[Test]
    #[Group('authorization')]
    public function it_answers_404_for_abandoning_another_users_sync(): void
    {
        $other = $this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters']);
        $sync = $this->stagingSync($other, 0, '1f2e3d4c-5b6a-4789-8abc-def012345678');
        $this->actingAsUploader();

        $this->deleteJson($this->syncUri($sync), [], $this->deviceHeaders())->assertNotFound();

        $this->assertSame(UploadOutcome::Staging, $sync->refresh()->outcome);
    }

    // ==================== helpers ====================

    /**
     * Authenticate as the uploader with the upload scope through a token bound to their desktop client.
     */
    private function actingAsUploader(): void
    {
        Passport::actingAs($this->uploader, ['grm:upload']);
        $this->bindToken($this->uploader, $this->desktopClient);
    }

    private function extendUri(Upload $sync): string
    {
        return route('api.grm.syncs.extend', $sync);
    }

    private function syncUri(Upload $sync): string
    {
        return route('api.grm.syncs.destroy', $sync);
    }

    private function commitUri(Upload $sync): string
    {
        return route('api.grm.syncs.commit', $sync);
    }

    private function stagingSync(User $user, int $baseRevision = 0, string $clientId = self::CLIENT_ID): Upload
    {
        $desktopClient = DesktopClient::find($clientId) ?? DesktopClient::factory()->create(['id' => $clientId, 'user_id' => $user->id]);
        RateLimiter::clear(md5('grm-sync-requests'.$user->id));

        return Upload::factory()->staging($desktopClient)->create([
            'user_id' => $user->id,
            'game_version_id' => $this->forever->id,
            'base_revision' => $baseRevision,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function openBody(array $overrides = [], ?User $user = null): array
    {
        $baseRevision = $overrides['baseRevision'] ?? 0;

        return [
            'schema' => 2,
            'gameVersion' => 'forever',
            'guildKey' => 'Regrowth',
            'addonVersion' => '1.2.3',
            'openTicket' => $this->openTicketFor($user ?? $this->uploader, $this->forever, $baseRevision),
            'baseRevision' => $baseRevision,
            'fileHash' => $this->fileHash(),
            ...$overrides,
        ];
    }

    private function fileHash(): string
    {
        return hash('sha256', 'SavedVariables');
    }

    private function revision(int $revision): RosterRevision
    {
        return RosterRevision::factory()->create([
            'game_version_id' => $this->forever->id,
            'revision' => $revision,
        ]);
    }

    private function openTicketFor(User $user, GameVersion $gameVersion, int $revision): string
    {
        return IssueOpenTicket::run($user, $gameVersion, $revision);
    }

    /**
     * Post a gzipped JSON body with the desktop client headers.
     *
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
}
