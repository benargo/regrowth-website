<?php

namespace App\Http\Controllers\Api\GuildRosterManager;

use App\Actions\GuildRosterManager\CheckSyncCanOpen;
use App\Actions\GuildRosterManager\CommitSync;
use App\Actions\GuildRosterManager\ExtendSyncTicket;
use App\Actions\GuildRosterManager\OpenSync;
use App\Enums\GuildRosterManager\CommitSyncStatus;
use App\Enums\GuildRosterManager\OpenSyncRefusal;
use App\Enums\GuildRosterManager\UploadOutcome;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureDesktopClient;
use App\Http\Middleware\EnsureSyncIsOpen;
use App\Http\Middleware\InflateGzipRequest;
use App\Http\Middleware\TouchDesktopClient;
use App\Http\Requests\GuildRosterManager\AbandonSyncRequest;
use App\Http\Requests\GuildRosterManager\CommitSyncRequest;
use App\Http\Requests\GuildRosterManager\ExtendSyncRequest;
use App\Http\Requests\GuildRosterManager\OpenSyncRequest;
use App\Models\DesktopClient;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\RosterRevision;
use App\Models\GuildRosterManager\Upload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Laravel\Passport\Http\Middleware\CheckToken;

#[Middleware(CheckToken::class.':grm:upload')]
#[Middleware(EnsureDesktopClient::class)]
#[Middleware(TouchDesktopClient::class)]
#[Authorize('uploadGuildRoster', DesktopClient::class)]
class SyncController extends Controller
{
    /**
     * Open a staging sync for the desktop client, answering `200` for a duplicate file and `409` for a lapsed
     * ticket or a moved revision without writing an upload.
     */
    #[Middleware(InflateGzipRequest::class.':required')]
    #[Middleware('throttle:grm-syncs')]
    public function store(OpenSyncRequest $request): JsonResponse
    {
        $sync = $request->sync();
        $gameVersion = GameVersion::where('slug', $sync->game_version)->firstOrFail();

        $result = CheckSyncCanOpen::run($gameVersion, $sync, $request->user());

        if ($result['refusal'] === OpenSyncRefusal::Duplicate) {
            return response()->json(['duplicate' => true, 'revision' => $result['revision']]);
        }

        if ($result['refusal'] !== null) {
            return response()->json([
                'status' => $result['refusal']->value,
                'revision' => $result['revision'],
            ], 409);
        }

        $upload = OpenSync::run(
            $gameVersion,
            $sync,
            $request->user(),
            $request->attributes->get('desktopClient'),
            $result['ticket_expires_at'],
        );

        return response()->json([
            'syncId' => $upload->id,
            'expiresAt' => $upload->expires_at->toIso8601ZuluString(),
            'ticketExpiresAt' => $upload->ticket_expires_at->toIso8601ZuluString(),
        ], 201);
    }

    /**
     * Commit the sync to its game version's roster state, replaying the result of an earlier commit.
     */
    #[Middleware(InflateGzipRequest::class.':required')]
    #[Middleware('throttle:grm-sync-requests')]
    public function commit(CommitSyncRequest $request, Upload $sync): JsonResponse
    {
        $result = CommitSync::run($sync, $request->ranks(), $request->removed());

        return match ($result->status) {
            CommitSyncStatus::Committed => response()->json([
                'status' => 'committed',
                'syncId' => $result->sync->id,
                'revision' => $result->revision,
                'committedAt' => RosterRevision::where('upload_id', $result->sync->id)->first()?->committed_at->toIso8601ZuluString(),
            ]),
            CommitSyncStatus::RevisionConflict,
            CommitSyncStatus::TicketExpired => response()->json([
                'status' => $result->status->value,
                'revision' => $result->revision,
            ], 409),
            CommitSyncStatus::Abandoned,
            CommitSyncStatus::Expired => response()->json(['status' => $result->status->value], 410),
        };
    }

    /**
     * Renew the open ticket of a staging sync.
     */
    #[Middleware(EnsureSyncIsOpen::class)]
    #[Middleware('throttle:grm-sync-requests')]
    public function extend(ExtendSyncRequest $request, Upload $sync): JsonResponse
    {
        $ticketExpiresAt = ExtendSyncTicket::run($sync);

        return response()->json([
            'ticketExpiresAt' => $ticketExpiresAt->toIso8601ZuluString(),
        ]);
    }

    /**
     * Abandon a staging sync and delete its staged rows, answering `204` when it is already abandoned or expired.
     */
    #[Middleware('throttle:grm-sync-requests')]
    public function destroy(AbandonSyncRequest $request, Upload $sync): Response|JsonResponse
    {
        if ($sync->outcome === UploadOutcome::Staging && ! $sync->is_expired) {
            $abandoned = Upload::staging()
                ->whereKey($sync->getKey())
                ->update([
                    'outcome' => UploadOutcome::Abandoned,
                    'expires_at' => null,
                    'ticket_expires_at' => null,
                ]);

            if ($abandoned === 1) {
                $sync->syncRows()->delete();

                return response()->noContent();
            }

            $sync->refresh();
        }

        if ($sync->is_expired || $sync->outcome === UploadOutcome::Abandoned) {
            return response()->noContent();
        }

        return response()->json([
            'status' => $sync->outcome === UploadOutcome::Superseded ? 'revision_conflict' : 'committed',
        ], 409);
    }
}
