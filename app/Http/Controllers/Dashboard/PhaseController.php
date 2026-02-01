<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdatePhaseGuildTagsRequest;
use App\Http\Requests\Dashboard\UpdatePhaseStartDateRequest;
use App\Http\Resources\TBC\PhaseResource;
use App\Http\Resources\WarcraftLogs\GuildTagResource;
use App\Models\TBC\Phase;
use App\Models\WarcraftLogs\GuildTag;
use App\Services\WarcraftLogs\GuildService as WarcraftLogsGuildService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PhaseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function listAll()
    {
        $phases = Phase::with(['raids', 'bosses', 'guildTags'])->orderBy('start_date', 'desc')->get();

        $currentPhase = $phases->firstWhere('start_date', '<=', now());
        $currentPhaseId = $currentPhase ? $currentPhase->id : null;

        return Inertia::render('Dashboard/ManagePhases', [
            'phases' => PhaseResource::collection($phases),
            'current_phase' => $currentPhaseId,
            'all_guild_tags' => Inertia::defer(fn () => $this->buildAllGuildTags()),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response(null, 405);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Phase $phase)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Phase $phase)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePhaseStartDateRequest $request, Phase $phase): RedirectResponse
    {
        $startDate = $request->validated('start_date');

        if ($startDate) {
            $startDate = Carbon::parse($startDate);
        }

        $phase->update([
            'start_date' => $startDate,
        ]);

        return back();
    }

    /**
     * Build all guild tags for selection.
     */
    public function buildAllGuildTags()
    {
        $allGuildTags = app(WarcraftLogsGuildService::class)->getGuild()->tags;

        return GuildTagResource::collection($allGuildTags);
    }

    /**
     * Update the guild tags associated with a phase.
     */
    public function updateGuildTags(UpdatePhaseGuildTagsRequest $request, Phase $phase): RedirectResponse
    {
        $guildTagIds = $request->validated('guild_tag_ids');

        // Remove this phase from all currently associated tags
        GuildTag::query()->where('tbc_phase_id', $phase->id)->update(['tbc_phase_id' => null]);

        // Associate the selected tags with this phase
        if (! empty($guildTagIds)) {
            GuildTag::query()->whereIn('id', $guildTagIds)->update(['tbc_phase_id' => $phase->id]);
        }

        return back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Phase $phase)
    {
        return response(null, 405);
    }
}
