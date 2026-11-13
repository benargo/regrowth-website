<?php

namespace App\Actions\GuildRosterManager;

use App\Data\GuildRosterManager\OpenSyncData;
use App\Enums\GuildRosterManager\OpenSyncRefusal;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\Upload;
use App\Models\User;
use Carbon\CarbonImmutable;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Decides whether a desktop sync may open, refusing a committed file hash, a missing or invalid ticket, or a moved revision.
 */
class CheckSyncCanOpen
{
    use AsAction;

    /**
     * @return array{refusal: ?OpenSyncRefusal, revision: int, ticket_expires_at: ?CarbonImmutable}
     */
    public function handle(GameVersion $gameVersion, OpenSyncData $sync, User $user): array
    {
        $duplicate = Upload::accepted()
            ->where('game_version_id', $gameVersion->id)
            ->where('idempotency_key', $sync->file_hash)
            ->first();

        if ($duplicate !== null) {
            return ['refusal' => OpenSyncRefusal::Duplicate, 'revision' => (int) $duplicate->revision, 'ticket_expires_at' => null];
        }

        $revision = $gameVersion->currentRosterRevision()->first()?->revision ?? 0;
        $ticketExpiresAt = VerifyOpenTicket::run($sync->open_ticket, $user, $gameVersion, $sync->base_revision);

        if ($ticketExpiresAt === null) {
            return ['refusal' => OpenSyncRefusal::TicketExpired, 'revision' => $revision, 'ticket_expires_at' => null];
        }

        if ($sync->base_revision !== $revision) {
            return ['refusal' => OpenSyncRefusal::RevisionConflict, 'revision' => $revision, 'ticket_expires_at' => null];
        }

        return ['refusal' => null, 'revision' => $revision, 'ticket_expires_at' => $ticketExpiresAt];
    }
}
