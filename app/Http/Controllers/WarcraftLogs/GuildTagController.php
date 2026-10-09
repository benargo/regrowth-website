<?php

namespace App\Http\Controllers\WarcraftLogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ToggleGuildTagAttendanceRequest;
use App\Models\WarcraftLogs\Guild;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class GuildTagController extends Controller
{
    /**
     * Toggle whether a guild tag's reports count toward attendance.
     */
    #[Authorize('update', 'guildTag')]
    public function toggleCountAttendance(ToggleGuildTagAttendanceRequest $request, Guild $guild, GuildTag $guildTag): RedirectResponse
    {
        $guildTag->update([
            'count_attendance' => $request->validated('count_attendance'),
        ]);

        return back();
    }
}
