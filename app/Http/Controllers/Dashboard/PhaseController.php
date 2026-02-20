<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdatePhaseGuildTagsRequest;
use App\Http\Requests\Dashboard\UpdatePhaseStartDateRequest;
use App\Http\Resources\TBC\PhaseResource;
use App\Http\Resources\WarcraftLogs\GuildTagResource;
use App\Models\TBC\Phase;
use App\Models\WarcraftLogs\GuildTag;
use App\Services\WarcraftLogs\GuildTags as WarcraftLogsGuildTagsService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class PhaseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function listAll(Request $request)
    {
        $phases = Phase::with(['raids', 'bosses', 'guildTags'])->orderBy('start_date', 'desc')->get();

        $currentPhase = $phases->firstWhere('start_date', '<=', now());
        $currentPhaseId = $currentPhase ? $currentPhase->id : null;

        return Inertia::render('Dashboard/ManagePhases', [
            'phases' => PhaseResource::collection($phases)->toArray($request),
            'current_phase' => $currentPhaseId,
            'all_guild_tags' => Inertia::defer(fn () => $this->buildAllGuildTags()),
        ]);
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

        $this->clearCache();

        return back();
    }

    /**
     * Build all guild tags for selection.
     */
    public function buildAllGuildTags()
    {
        $allGuildTags = app(WarcraftLogsGuildTagsService::class)->toCollection();

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

        $this->clearCache();

        return back();
    }

    /**
     * Clear relevant caches when phases are updated.
     */
    protected function clearCache(): void
    {
        Cache::forget('phases.tbc.index');
    }
}
