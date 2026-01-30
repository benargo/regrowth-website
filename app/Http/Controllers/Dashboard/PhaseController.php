<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdatePhaseStartDateRequest;
use App\Http\Resources\TBC\PhaseResource;
use App\Models\TBC\Phase;
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
        $phases = Phase::with(['raids', 'bosses'])->orderBy('start_date', 'desc')->get();

        $currentPhase = $phases->firstWhere('start_date', '<=', now());
        $currentPhaseId = $currentPhase ? $currentPhase->id : null;

        return Inertia::render('Dashboard/ManagePhases', [
            'phases' => PhaseResource::collection($phases),
            'current_phase' => $currentPhaseId,
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
     * Remove the specified resource from storage.
     */
    public function destroy(Phase $phase)
    {
        return response(null, 405);
    }
}
