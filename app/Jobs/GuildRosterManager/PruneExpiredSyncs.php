<?php

namespace App\Jobs\GuildRosterManager;

use App\Enums\GuildRosterManager\UploadOutcome;
use App\Models\GuildRosterManager\SyncRow;
use App\Models\GuildRosterManager\Upload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Marks staging syncs past their expiry as expired, and deletes the staged rows of every upload that is no longer staging.
 */
class PruneExpiredSyncs implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $expired = Upload::staging()
            ->where('expires_at', '<=', now())
            ->update([
                'outcome' => UploadOutcome::Expired,
                'expires_at' => null,
                'ticket_expires_at' => null,
            ]);

        $deleted = SyncRow::whereIn('upload_id', Upload::select('id')->whereNot('outcome', UploadOutcome::Staging))->delete();

        if ($expired > 0 || $deleted > 0) {
            Log::info('Pruned GRM sync rows.', ['expired' => $expired, 'deleted' => $deleted]);
        }
    }
}
