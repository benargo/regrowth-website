<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreGuildRankRequest;
use App\Http\Requests\Dashboard\ToggleGuildRankAttendanceRequest;
use App\Http\Requests\Dashboard\UpdateGuildRankPositionsRequest;
use App\Http\Requests\Dashboard\UpdateGuildRankRequest;
use App\Models\GuildRank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class GuildRankController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function manageRanks(Request $request)
    {
        return Inertia::render('Dashboard/ManageRanks', [
            'guildRanks' => GuildRank::orderBy('position')->get(),
        ]);
    }

    /**
     * Store a newly created resource.
     */
    public function store(StoreGuildRankRequest $request): RedirectResponse
    {
        $nextPosition = GuildRank::max('position') + 1;

        GuildRank::create([
            'name' => $request->validated('name'),
            'position' => $nextPosition,
        ]);

        return back();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGuildRankRequest $request, GuildRank $guildRank): RedirectResponse
    {
        $guildRank->update($request->validated());

        return back();
    }

    /**
     * Update positions for all ranks.
     */
    public function updatePositions(UpdateGuildRankPositionsRequest $request): RedirectResponse
    {
        $ranks = $request->validated('ranks');

        DB::transaction(function () use ($ranks) {
            // First, set all positions to negative values to avoid unique constraint conflicts
            foreach ($ranks as $index => $rankData) {
                GuildRank::where('id', $rankData['id'])->update(['position' => -($index + 1)]);
            }

            // Then set the final positions
            foreach ($ranks as $rankData) {
                GuildRank::where('id', $rankData['id'])->update(['position' => $rankData['position']]);
            }
        });

        return back();
    }

    /**
     * Toggle the count_attendance flag for a guild rank.
     */
    public function toggleCountAttendance(ToggleGuildRankAttendanceRequest $request, GuildRank $guildRank): RedirectResponse
    {
        $guildRank->update([
            'count_attendance' => $request->validated('count_attendance'),
        ]);

        return back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GuildRank $guildRank): RedirectResponse
    {
        Gate::authorize('delete', $guildRank);

        $guildRank->delete();

        return back();
    }
}
