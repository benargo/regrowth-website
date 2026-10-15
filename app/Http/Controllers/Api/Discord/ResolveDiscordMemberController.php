<?php

namespace App\Http\Controllers\Api\Discord;

use App\Http\Controllers\Controller;
use App\Jobs\Discord\ResolveGuildMember;
use App\Models\PlannedAbsence;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

/**
 * Makes sure a Discord member picked in UserSearch has a users row, without calling
 * Discord inside the request. Responds 200 `ready` when the row exists; otherwise
 * queues ResolveGuildMember on `high` and responds 202 `pending`, and the page waits
 * for the DiscordMemberResolved broadcast.
 *
 * Authorised for users who can create planned absences.
 */
#[Middleware('throttle:30,1')]
#[Authorize('create', PlannedAbsence::class)]
class ResolveDiscordMemberController extends Controller
{
    public function __invoke(Request $request, string $member): JsonResponse
    {
        if (User::whereKey($member)->exists()) {
            return response()->json(['status' => 'ready']);
        }

        ResolveGuildMember::dispatch($member, $request->user()->id);

        return response()->json(['status' => 'pending'], 202);
    }
}
