<?php

namespace Tests\Feature\Jobs\GuildRosterManager;

use App\Enums\GuildRosterManager\UploadOutcome;
use App\Jobs\GuildRosterManager\PruneExpiredSyncs;
use App\Models\GuildRosterManager\Ban;
use App\Models\GuildRosterManager\RosterEntry;
use App\Models\GuildRosterManager\RosterRevision;
use App\Models\GuildRosterManager\SyncRow;
use App\Models\GuildRosterManager\Upload;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class PruneExpiredSyncsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();
    }

    #[Test]
    public function it_expires_staging_syncs_past_their_expiry_and_deletes_their_rows(): void
    {
        $sync = Upload::factory()->staging()->create(['expires_at' => now()->subMinute()]);
        SyncRow::factory()->count(2)->create(['upload_id' => $sync->id]);
        Log::spy();

        (new PruneExpiredSyncs)->handle();

        $sync->refresh();
        $this->assertSame(UploadOutcome::Expired, $sync->outcome);
        $this->assertNull($sync->expires_at);
        $this->assertNull($sync->ticket_expires_at);
        $this->assertSame(0, SyncRow::count());
        Log::shouldHaveReceived('info')->with('Pruned GRM sync rows.', ['expired' => 1, 'deleted' => 2])->once();
    }

    #[Test]
    public function it_leaves_an_open_sync_alone(): void
    {
        $sync = Upload::factory()->staging()->create();
        SyncRow::factory()->create(['upload_id' => $sync->id]);
        Log::spy();

        (new PruneExpiredSyncs)->handle();

        $this->assertSame(UploadOutcome::Staging, $sync->refresh()->outcome);
        $this->assertSame(1, SyncRow::count());
        Log::shouldNotHaveReceived('info');
    }

    #[Test]
    public function it_deletes_rows_left_on_abandoned_and_superseded_syncs(): void
    {
        foreach ([UploadOutcome::Abandoned, UploadOutcome::Superseded, UploadOutcome::Expired] as $outcome) {
            $sync = Upload::factory()->staging()->create();
            $sync->markOutcome($outcome, ['expires_at' => null, 'ticket_expires_at' => null]);
            SyncRow::factory()->create(['upload_id' => $sync->id]);
        }

        (new PruneExpiredSyncs)->handle();

        $this->assertSame(0, SyncRow::count());
    }

    #[Test]
    public function it_deletes_rows_left_on_a_committed_sync(): void
    {
        $sync = Upload::factory()->staging()->create();
        $sync->markOutcome(UploadOutcome::Processed, ['expires_at' => null, 'ticket_expires_at' => null]);
        SyncRow::factory()->create(['upload_id' => $sync->id]);

        (new PruneExpiredSyncs)->handle();

        $this->assertSame(0, SyncRow::count());
    }

    #[Test]
    public function it_never_deletes_uploads_or_roster_state(): void
    {
        $sync = Upload::factory()->staging()->create(['expires_at' => now()->subMinute()]);
        SyncRow::factory()->create(['upload_id' => $sync->id]);
        RosterEntry::factory()->create();
        RosterRevision::factory()->create();
        Ban::factory()->create();
        $counts = [Upload::count(), RosterEntry::count(), RosterRevision::count(), Ban::count()];

        (new PruneExpiredSyncs)->handle();

        $this->assertSame($counts, [Upload::count(), RosterEntry::count(), RosterRevision::count(), Ban::count()]);
    }

    #[Test]
    #[Group('contract')]
    public function it_is_scheduled_every_ten_minutes(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => $event->description === 'prune-expired-grm-syncs');

        $this->assertNotNull($event);
        $this->assertSame('*/10 * * * *', $event->expression);
    }
}
