<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdatePhaseGuildTagsRequest;
use App\Http\Requests\Dashboard\UpdatePhaseStartDateRequest;
use App\Http\Resources\PhaseResource;
use App\Http\Resources\WarcraftLogs\GuildTagResource;
use App\Models\Phase;
use App\Models\WarcraftLogs\GuildTag;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

#[Authorize('view-officer-dashboard')]
class PhaseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $phases = Phase::with(['raids.bosses.media', 'guildTags'])->orderBy('number')->get();

        $currentPhase = $phases->firstWhere('start_date', '<=', now());

        return Inertia::render('Manage/Phases/Index', [
            'phases' => PhaseResource::collection($phases)->resolve($request),
            'current_phase' => $currentPhase?->id ?? null,
            'all_guild_tags' => Inertia::defer(fn () => $this->buildAllGuildTags()),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    #[Authorize('update', 'phase')]
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
     * Update the guild tags associated with a phase.
     */
    #[Authorize('update', 'phase')]
    public function updateGuildTags(UpdatePhaseGuildTagsRequest $request, Phase $phase): RedirectResponse
    {
        $guildTagIds = $request->validated('guild_tag_ids');

        // Update tags one at a time so GuildTagObserver re-resolves their reports' game versions.
        DB::transaction(function () use ($phase, $guildTagIds): void {
            foreach (GuildTag::whereBelongsTo($phase)->whereKeyNot($guildTagIds)->get() as $guildTag) {
                $guildTag->update(['phase_id' => null]);
            }

            foreach (GuildTag::whereKey($guildTagIds)->get() as $guildTag) {
                $guildTag->update(['phase_id' => $phase->id]);
            }
        });

        return back();
    }

    /**
     * Build all guild tags for selection.
     */
    private function buildAllGuildTags(): AnonymousResourceCollection
    {
        $allGuildTags = GuildTag::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return GuildTagResource::collection($allGuildTags);
    }
}
