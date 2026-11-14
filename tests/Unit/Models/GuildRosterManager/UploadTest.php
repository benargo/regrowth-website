<?php

namespace Tests\Unit\Models\GuildRosterManager;

use App\Enums\GuildRosterManager\UploadOutcome;
use App\Models\DesktopClient;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\SyncRow;
use App\Models\GuildRosterManager\Upload;
use App\Models\GuildRosterManager\UploadRow;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

#[Group('characters')]
class UploadTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return Upload::class;
    }

    #[Test]
    public function it_has_the_expected_fillable_attributes(): void
    {
        $this->assertFillableAttribute($this->make(), [
            'game_version_id',
            'user_id',
            'source',
            'desktop_client_id',
            'idempotency_key',
            'outcome',
            'base_revision',
            'revision',
            'expires_at',
            'ticket_expires_at',
            'addon_version',
            'captured_at',
            'member_count',
            'storage_path',
            'processed_at',
            'processed_count',
            'skipped_count',
            'warning_count',
            'error_count',
        ]);
    }

    #[Test]
    public function row_counts_default_to_zero_and_are_cast_to_integers(): void
    {
        $upload = $this->create()->fresh();

        $this->assertSame(0, $upload->processed_count);
        $this->assertSame(0, $upload->skipped_count);
        $this->assertSame(0, $upload->warning_count);
        $this->assertSame(0, $upload->error_count);

        $upload->update(['processed_count' => '12', 'error_count' => '3']);

        $this->assertSame(12, $upload->fresh()->processed_count);
        $this->assertSame(3, $upload->fresh()->error_count);
    }

    // ==================== relationships ====================

    #[Test]
    public function it_belongs_to_a_game_version_a_user_and_a_device(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $user = User::factory()->create();
        $device = DesktopClient::factory()->create(['user_id' => $user->id]);

        $upload = Upload::factory()->for($gameVersion)->for($user)->staging($device)->create();

        $this->assertTrue($upload->gameVersion->is($gameVersion));
        $this->assertTrue($upload->user->is($user));
        $this->assertTrue($upload->device->is($device));
    }

    #[Test]
    public function it_has_many_upload_rows_and_sync_rows(): void
    {
        $upload = Upload::factory()->staging()->create();
        $uploadRows = UploadRow::factory()->for($upload)->count(2)->create();
        $syncRow = SyncRow::factory()->for($upload)->create();
        UploadRow::factory()->create();

        $this->assertEqualsCanonicalizing($uploadRows->modelKeys(), $upload->rows->modelKeys());
        $this->assertSame([$syncRow->getKey()], $upload->syncRows->modelKeys());
    }

    #[Test]
    public function deleting_an_upload_deletes_its_rows(): void
    {
        $upload = Upload::factory()->staging()->create();
        $uploadRow = UploadRow::factory()->for($upload)->create();
        $syncRow = SyncRow::factory()->for($upload)->create();

        $upload->delete();

        $this->assertModelMissing($uploadRow);
        $this->assertModelMissing($syncRow);
    }

    // ==================== scopes ====================

    #[Test]
    public function staging_includes_only_syncs_being_staged(): void
    {
        $staging = Upload::factory()->staging()->create();

        foreach (UploadOutcome::cases() as $outcome) {
            if ($outcome !== UploadOutcome::Staging) {
                Upload::factory()->withOutcome($outcome)->create();
            }
        }

        $this->assertSame([$staging->id], Upload::staging()->pluck('id')->all());
    }

    // ==================== is_expired ====================

    #[Test]
    public function is_expired_is_true_for_an_expired_outcome_without_an_expiry_time(): void
    {
        $upload = Upload::factory()->withOutcome(UploadOutcome::Expired)->create(['expires_at' => null]);

        $this->assertTrue($upload->is_expired);
    }

    #[Test]
    public function is_expired_is_false_for_a_staging_sync_without_an_expiry_time(): void
    {
        $upload = Upload::factory()->staging()->create(['expires_at' => null]);

        $this->assertFalse($upload->is_expired);
    }

    // ==================== is_ticket_expired ====================

    #[Test]
    public function is_ticket_expired_is_true_for_a_staging_sync_without_a_ticket(): void
    {
        $upload = Upload::factory()->staging()->create(['ticket_expires_at' => null]);

        $this->assertTrue($upload->is_ticket_expired);
    }

    #[Test]
    public function is_ticket_expired_is_false_once_the_sync_is_no_longer_staging(): void
    {
        $upload = Upload::factory()->committed(1)->create(['ticket_expires_at' => now()->subHour()]);

        $this->assertFalse($upload->is_ticket_expired);
    }

    // ==================== markOutcome ====================

    #[Test]
    #[DataProvider('completedOutcomes')]
    public function mark_outcome_sets_processed_at_to_now_for_completed_outcomes(UploadOutcome $outcome): void
    {
        $this->freezeSecond();
        $upload = $this->create();

        $upload->markOutcome($outcome);

        $this->assertTrue($upload->fresh()->processed_at->eq(now()));
    }

    #[Test]
    public function mark_outcome_keeps_a_given_processed_at(): void
    {
        $upload = $this->create();
        $processedAt = now()->subDay()->startOfSecond();

        $upload->markOutcome(UploadOutcome::Processed, ['processed_at' => $processedAt]);

        $this->assertTrue($upload->fresh()->processed_at->eq($processedAt));
    }

    #[Test]
    public function mark_outcome_leaves_processed_at_empty_for_other_outcomes(): void
    {
        $upload = $this->create();

        $upload->markOutcome(UploadOutcome::Failed);

        $this->assertSame(UploadOutcome::Failed, $upload->fresh()->outcome);
        $this->assertNull($upload->fresh()->processed_at);
    }

    /**
     * @return array<string, array{UploadOutcome}>
     */
    public static function completedOutcomes(): array
    {
        return [
            'processed' => [UploadOutcome::Processed],
            'no changes' => [UploadOutcome::NoChanges],
        ];
    }
}
