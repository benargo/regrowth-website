<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdatePhaseStartDateRequest;
use App\Http\Resources\PhaseResource;
use App\Models\Phase;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
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
        $phases = Phase::with('raids.bosses.media')->orderBy('number')->get();

        $currentPhase = $phases->firstWhere('start_date', '<=', now());

        return Inertia::render('Manage/Phases/Index', [
            'phases' => PhaseResource::collection($phases)->resolve($request),
            'current_phase' => $currentPhase?->id,
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
}
