<?php

namespace Tests\Feature\Models\GuildRosterManager;

use App\Actions\GuildRosterManager\StoreUpload;
use App\Enums\GuildRosterManager\UploadOutcome;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\Upload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class UploadTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_uses_the_grm_uploads_table(): void
    {
        $this->assertSame('grm_uploads', (new Upload)->getTable());
    }

    #[Test]
    public function it_uses_a_uuid_primary_key(): void
    {
        Storage::fake('local');
        Queue::fake();
        $factoryUpload = Upload::factory()->create();
        $gameVersion = GameVersion::factory()->create(['slug' => 'tbc']);

        StoreUpload::run($gameVersion, 'csv', ['delimiter' => ',', 'headers' => [], 'rows' => []], User::factory()->create());

        $this->assertTrue(Str::isUuid($factoryUpload->id));
        $manual = Upload::where('game_version_id', $gameVersion->id)->firstOrFail();
        $this->assertTrue(Str::isUuid($manual->id));
    }

    #[Test]
    public function accepted_excludes_staging_abandoned_expired_and_superseded(): void
    {
        foreach (UploadOutcome::cases() as $outcome) {
            Upload::factory()->withOutcome($outcome)->create();
        }

        $outcomes = Upload::accepted()->pluck('outcome')->all();

        $this->assertEqualsCanonicalizing([
            UploadOutcome::Received,
            UploadOutcome::Processing,
            UploadOutcome::Processed,
            UploadOutcome::NoChanges,
            UploadOutcome::Failed,
        ], $outcomes);
    }

    #[Test]
    public function is_expired_is_true_for_a_staging_sync_past_its_expiry(): void
    {
        $upload = Upload::factory()->staging()->create();

        $this->assertFalse($upload->is_expired);

        $this->travel(61)->minutes();

        $this->assertTrue($upload->fresh()->is_expired);
    }

    #[Test]
    public function is_expired_is_false_for_a_committed_sync_with_an_old_expiry(): void
    {
        $upload = Upload::factory()->committed(1)->create(['expires_at' => now()->subDay()]);

        $this->assertFalse($upload->is_expired);
    }

    #[Test]
    public function is_ticket_expired_is_true_after_the_ticket_lapses(): void
    {
        $upload = Upload::factory()->staging()->create();

        $this->assertFalse($upload->is_ticket_expired);

        $this->travel(301)->seconds();

        $this->assertTrue($upload->fresh()->is_ticket_expired);
    }

    #[Test]
    public function mark_outcome_saves_the_outcome_and_attributes(): void
    {
        $upload = Upload::factory()->staging()->create();

        $upload->markOutcome(UploadOutcome::Received, ['revision' => 4]);

        $fresh = $upload->fresh();
        $this->assertSame(UploadOutcome::Received, $fresh->outcome);
        $this->assertSame(4, $fresh->revision);
    }

    #[Test]
    public function it_allows_the_same_file_hash_twice(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $hash = hash('sha256', 'same file');

        Upload::factory()->for($gameVersion)->desktop($hash)->withOutcome(UploadOutcome::Superseded)->create();
        Upload::factory()->for($gameVersion)->staging()->create(['idempotency_key' => $hash]);

        $this->assertSame(2, Upload::where('idempotency_key', $hash)->count());
    }

    #[Test]
    public function staging_creates_a_desktop_client_for_the_same_user(): void
    {
        $default = Upload::factory()->staging()->create();
        $user = User::factory()->create();
        $forUser = Upload::factory()->for($user)->staging()->create();

        $this->assertSame($default->user_id, $default->device->user_id);
        $this->assertSame($user->id, $forUser->user_id);
        $this->assertSame($user->id, $forUser->device->user_id);
    }
}
