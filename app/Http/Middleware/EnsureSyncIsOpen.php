<?php

namespace App\Http\Middleware;

use App\Enums\GuildRosterManager\UploadOutcome;
use App\Models\GuildRosterManager\Upload;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Passes only requests for an open staging sync: `409` for a committed or superseded sync, `410` for an abandoned or expired one.
 */
class EnsureSyncIsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Upload $sync */
        $sync = $request->route('sync');

        if ($sync->is_expired) {
            return response()->json(['status' => 'expired'], 410);
        }

        return match ($sync->outcome) {
            UploadOutcome::Staging => $next($request),
            UploadOutcome::Superseded => response()->json(['status' => 'revision_conflict'], 409),
            UploadOutcome::Abandoned => response()->json(['status' => 'abandoned'], 410),
            default => response()->json(['status' => 'committed'], 409),
        };
    }
}
