<?php

namespace App\Http\Controllers\WarcraftLogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ToggleGuildTagAttendanceRequest;
use App\Models\GuildTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth')]
class GuildTagController extends Controller
{
    /**
     * Toggle the count_attendance flag for a guild tag.
     */
    #[Authorize('update', 'guildTag')]
    public function toggleCountAttendance(ToggleGuildTagAttendanceRequest $request, GuildTag $guildTag): RedirectResponse
    {
        $guildTag->update([
            'count_attendance' => $request->validated('count_attendance'),
        ]);

        return back();
    }
}
