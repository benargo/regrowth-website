<?php

namespace App\Http\Controllers\WarcraftLogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ToggleGuildTagAttendanceRequest;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Http\RedirectResponse;

class GuildTagController extends Controller
{
    /**
     * Toggle the count_attendance flag for a guild tag.
     */
    public function toggleCountAttendance(ToggleGuildTagAttendanceRequest $request, GuildTag $guildTag): RedirectResponse
    {
        $guildTag->update([
            'count_attendance' => $request->validated('count_attendance'),
        ]);

        return back();
    }
}
