<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdatePhaseGuildTagsRequest;
use App\Http\Requests\Dashboard\UpdatePhaseStartDateRequest;
use App\Http\Resources\PhaseResource;
use App\Http\Resources\WarcraftLogs\GuildTagResource;
use App\Models\GuildTag;
use App\Models\Phase;
use App\Services\WarcraftLogs\GuildTags as WarcraftLogsGuildTagsService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;

class PhaseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $phases = Phase::with(['raids', 'bosses', 'guildTags'])->orderBy('number')->get();

        $currentPhase = $phases->firstWhere('start_date', '<=', now());

        return Inertia::render('Dashboard/ManagePhases', [
            'phases' => PhaseResource::collection($phases)->toArray($request),
            'current_phase' => $currentPhase?->id ?? null,
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

        return back();
    }

    /**
     * Build all guild tags for selection.
     */
    public function buildAllGuildTags(): AnonymousResourceCollection
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

        return back();
    }
}
