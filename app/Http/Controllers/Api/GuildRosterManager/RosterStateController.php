<?php

namespace App\Http\Controllers\Api\GuildRosterManager;

use App\Actions\GuildRosterManager\IssueOpenTicket;
use App\Data\GuildRosterManager\RosterStateData;
use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureDesktopClient;
use App\Http\Middleware\TouchDesktopClient;
use App\Http\Resources\GuildRosterManager\RosterStateResource;
use App\Models\DesktopClient;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\Ban;
use App\Models\GuildRosterManager\RosterEntry;
use App\Models\GuildRosterManager\RosterRevision;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Laravel\Passport\Http\Middleware\CheckToken;
use Symfony\Component\HttpFoundation\Response;

#[Middleware(CheckToken::class.':grm:upload')]
#[Middleware(EnsureDesktopClient::class)]
#[Middleware(TouchDesktopClient::class)]
#[Middleware('throttle:grm-sync-requests')]
#[Authorize('uploadGuildRoster', DesktopClient::class)]
class RosterStateController extends Controller
{
    /**
     * Return a game version's current roster revision and fingerprints, or 304 when the client's ETag still matches.
     */
    public function __invoke(Request $request, GameVersion $gameVersion): Response
    {
        abort_unless(
            GameVersion::currentRosters()->contains(fn (GameVersion $current): bool => $current->is($gameVersion) && $current->wow_client_folder !== null),
            404,
        );

        $user = $request->user();
        $banAccess = [
            'create' => $user->can('create', Ban::class),
            'update' => $user->can('update', Ban::class),
        ];

        $revision = $gameVersion->currentRosterRevision()->first();
        $revisionNumber = $revision?->revision ?? 0;
        $etag = sprintf('"%s-r%d-b%d%d"', $gameVersion->slug, $revisionNumber, $banAccess['create'], $banAccess['update']);
        $headers = [
            'ETag' => $etag,
            'Cache-Control' => 'private, no-cache',
            'X-GRM-Open-Ticket' => IssueOpenTicket::run($user, $gameVersion, $revisionNumber),
        ];

        if (str((string) $request->header('If-None-Match'))->chopStart('W/')->toString() === $etag) {
            return response('', 304, $headers);
        }

        return (new RosterStateResource($this->rosterState($gameVersion, $revision, $banAccess)))
            ->response()
            ->withHeaders($headers);
    }

    /**
     * Build the game version's roster state from its current revision and the fingerprint of every roster entry the user may see.
     *
     * @param  array{create: bool, update: bool}  $banAccess
     */
    private function rosterState(GameVersion $gameVersion, ?RosterRevision $revision, array $banAccess): RosterStateData
    {
        $includeBans = $banAccess['create'] || $banAccess['update'];

        $groups = collect(RosterEntryKind::cases())
            ->reject(fn (RosterEntryKind $kind): bool => $kind === RosterEntryKind::Ban && ! $includeBans)
            ->mapWithKeys(fn (RosterEntryKind $kind): array => [$kind->fingerprintGroup() => []])
            ->all();

        RosterEntry::select(['kind', 'key', 'fingerprint'])
            ->where('game_version_id', $gameVersion->id)
            ->when(! $includeBans, fn ($query) => $query->where('kind', '!=', RosterEntryKind::Ban))
            ->get()
            ->each(function (RosterEntry $entry) use (&$groups): void {
                $groups[$entry->kind->fingerprintGroup()][$entry->key] = $entry->fingerprint;
            });

        return new RosterStateData(
            slug: $gameVersion->slug,
            revision: $revision?->revision ?? 0,
            committed_at: $revision?->committed_at,
            fingerprints: [...$groups, 'ranks' => $revision?->ranks_fingerprint],
            ban_access: $banAccess,
        );
    }
}
