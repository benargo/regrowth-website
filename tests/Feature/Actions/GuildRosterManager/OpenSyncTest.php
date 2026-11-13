<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\IssueOpenTicket;
use App\Actions\GuildRosterManager\OpenSync;
use App\Actions\GuildRosterManager\VerifyOpenTicket;
use App\Data\GuildRosterManager\OpenSyncData;
use App\Enums\GuildRosterManager\UploadOutcome;
use App\Enums\GuildRosterManager\UploadSource;
use App\Models\DesktopClient;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\SyncRow;
use App\Models\GuildRosterManager\Upload;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class OpenSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private GameVersion $gameVersion;

    private DesktopClient $desktopClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();
        $this->user = User::factory()->create();
        $this->gameVersion = GameVersion::factory()->create();
        $this->desktopClient = DesktopClient::factory()->create(['user_id' => $this->user->id]);
    }

    #[Test]
    public function it_opens_a_staging_sync(): void
    {
        $ticketExpiresAt = VerifyOpenTicket::run(IssueOpenTicket::run($this->user, $this->gameVersion, 3), $this->user, $this->gameVersion, 3);

        $upload = OpenSync::run($this->gameVersion, $this->sync(3), $this->user, $this->desktopClient, $ticketExpiresAt);

        $upload->refresh();
        $this->assertTrue(Str::isUuid($upload->id));
        $this->assertSame(UploadOutcome::Staging, $upload->outcome);
        $this->assertSame(UploadSource::Desktop, $upload->source);
        $this->assertSame($this->gameVersion->id, $upload->game_version_id);
        $this->assertSame($this->user->id, $upload->user_id);
        $this->assertSame($this->desktopClient->id, $upload->desktop_client_id);
        $this->assertSame($this->fileHash(), $upload->idempotency_key);
        $this->assertSame(3, $upload->base_revision);
        $this->assertNull($upload->revision);
        $this->assertSame('1.2.3', $upload->addon_version);
        $this->assertSame(0, $upload->member_count);
        $this->assertSame(now()->getTimestamp(), $upload->captured_at->getTimestamp());
        $this->assertSame(now()->addHour()->getTimestamp(), $upload->expires_at->getTimestamp());
        $this->assertSame($ticketExpiresAt->getTimestamp(), $upload->ticket_expires_at->getTimestamp());
    }

    #[Test]
    public function it_abandons_the_officers_open_sync_for_the_game_version(): void
    {
        $earlier = Upload::factory()->staging()->create([
            'game_version_id' => $this->gameVersion->id,
            'user_id' => $this->user->id,
        ]);
        SyncRow::factory()->count(2)->create(['upload_id' => $earlier->id]);

        $this->assertNotSame($this->desktopClient->id, $earlier->desktop_client_id);

        OpenSync::run($this->gameVersion, $this->sync(0), $this->user, $this->desktopClient, CarbonImmutable::now()->addMinutes(5));

        $earlier->refresh();
        $this->assertSame(UploadOutcome::Abandoned, $earlier->outcome);
        $this->assertNull($earlier->expires_at);
        $this->assertNull($earlier->ticket_expires_at);
        $this->assertSame(0, $earlier->syncRows()->count());
        $this->assertSame(1, Upload::staging()->count());
    }

    #[Test]
    public function it_leaves_another_officers_open_sync_alone(): void
    {
        $other = Upload::factory()->staging()->create(['game_version_id' => $this->gameVersion->id]);
        SyncRow::factory()->create(['upload_id' => $other->id]);

        OpenSync::run($this->gameVersion, $this->sync(0), $this->user, $this->desktopClient, CarbonImmutable::now()->addMinutes(5));

        $this->assertSame(UploadOutcome::Staging, $other->refresh()->outcome);
        $this->assertSame(1, $other->syncRows()->count());
    }

    #[Test]
    public function it_leaves_the_officers_open_sync_for_another_version_alone(): void
    {
        $other = Upload::factory()->staging($this->desktopClient)->create(['user_id' => $this->user->id]);
        SyncRow::factory()->create(['upload_id' => $other->id]);

        OpenSync::run($this->gameVersion, $this->sync(0), $this->user, $this->desktopClient, CarbonImmutable::now()->addMinutes(5));

        $this->assertSame(UploadOutcome::Staging, $other->refresh()->outcome);
        $this->assertSame(1, $other->syncRows()->count());
    }

    #[Test]
    public function it_never_lets_the_ticket_outlive_the_sync(): void
    {
        $upload = OpenSync::run($this->gameVersion, $this->sync(0), $this->user, $this->desktopClient, CarbonImmutable::now()->addHours(2));

        $this->assertSame(now()->addHour()->getTimestamp(), $upload->refresh()->ticket_expires_at->getTimestamp());
    }

    // ==================== helpers ====================

    private function sync(int $baseRevision): OpenSyncData
    {
        return new OpenSyncData(
            game_version: $this->gameVersion->slug,
            guild_key: $this->gameVersion->grm_guild_key,
            addon_version: '1.2.3',
            base_revision: $baseRevision,
            open_ticket: 'ticket',
            file_hash: $this->fileHash(),
        );
    }

    private function fileHash(): string
    {
        return hash('sha256', 'SavedVariables');
    }
}
