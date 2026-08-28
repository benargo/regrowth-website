<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreGuildRankRequest;
use App\Http\Requests\Dashboard\ToggleGuildRankAttendanceRequest;
use App\Http\Requests\Dashboard\UpdateGuildRankPositionsRequest;
use App\Http\Requests\Dashboard\UpdateGuildRankRequest;
use App\Models\GuildRank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

#[Authorize('view-officer-dashboard')]
class GuildRankController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function list()
    {
        $guildRanks = GuildRank::hydrate(
            Cache::remember('guild_ranks:index', now()->addDay(), function () {
                return GuildRank::ordered()->get()->toArray();
            })
        );

        return Inertia::render('Manage/Ranks/Index', [
            'guildRanks' => $guildRanks,
        ]);
    }

    /**
     * Store a newly created resource.
     */
    #[Authorize('edit-datasets')]
    public function store(StoreGuildRankRequest $request): RedirectResponse
    {
        GuildRank::create([
            'name' => $request->validated('name'),
        ]);

        return $this->back();
    }

    /**
     * Update the specified resource in storage.
     */
    #[Authorize('edit-datasets')]
    public function update(UpdateGuildRankRequest $request, GuildRank $guildRank): RedirectResponse
    {
        $guildRank->update($request->validated());

        return $this->back();
    }

    /**
     * Update positions for all ranks.
     */
    #[Authorize('edit-datasets')]
    public function updatePositions(UpdateGuildRankPositionsRequest $request): RedirectResponse
    {
        GuildRank::setNewOrder(
            collect($request->validated('ranks'))->sortBy('position')->pluck('id')->all(),
            startOrder: 0,
        );

        return $this->back();
    }

    /**
     * Toggle the count_attendance flag for a guild rank.
     */
    #[Authorize('update', 'guildRank')]
    public function toggleCountAttendance(ToggleGuildRankAttendanceRequest $request, GuildRank $guildRank): RedirectResponse
    {
        $guildRank->count_attendance = $request->validated('count_attendance');
        $guildRank->save();

        return $this->back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GuildRank $guildRank): RedirectResponse
    {
        Gate::authorize('delete', $guildRank);

        $guildRank->delete();

        return $this->back();
    }

    /**
     * Redirect back with cache cleared.
     */
    private function back(): RedirectResponse
    {
        $this->clearCache();

        return back();
    }

    /**
     * Clear the guild ranks cache.
     */
    private function clearCache(): void
    {
        Cache::forget('guild_ranks:index');
    }
}
