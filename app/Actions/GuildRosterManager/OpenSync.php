<?php

namespace App\Actions\GuildRosterManager;

use App\Data\GuildRosterManager\OpenSyncData;
use App\Enums\GuildRosterManager\UploadOutcome;
use App\Enums\GuildRosterManager\UploadSource;
use App\Models\DesktopClient;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\Upload;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Opens a staging desktop sync, abandoning the uploader's other open syncs for the game version and deleting their rows.
 */
class OpenSync
{
    use AsAction;

    public function handle(
        GameVersion $gameVersion,
        OpenSyncData $sync,
        User $uploader,
        DesktopClient $desktopClient,
        CarbonImmutable $ticketExpiresAt,
    ): Upload {
        return DB::transaction(function () use ($gameVersion, $sync, $uploader, $desktopClient, $ticketExpiresAt): Upload {
            $openSyncs = Upload::staging()
                ->where('game_version_id', $gameVersion->id)
                ->where('user_id', $uploader->id)
                ->get();

            foreach ($openSyncs as $openSync) {
                $openSync->markOutcome(UploadOutcome::Abandoned, ['expires_at' => null, 'ticket_expires_at' => null]);
                $openSync->syncRows()->delete();
            }

            $expiresAt = now()->addHour();

            return Upload::forceCreate([
                'game_version_id' => $gameVersion->id,
                'user_id' => $uploader->id,
                'source' => UploadSource::Desktop,
                'desktop_client_id' => $desktopClient->id,
                'idempotency_key' => $sync->file_hash,
                'outcome' => UploadOutcome::Staging,
                'base_revision' => $sync->base_revision,
                'addon_version' => $sync->addon_version,
                'captured_at' => now(),
                'member_count' => 0,
                'expires_at' => $expiresAt,
                'ticket_expires_at' => $ticketExpiresAt->min($expiresAt),
            ]);
        });
    }
}
