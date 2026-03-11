<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlannedAbsenceResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * Display the authenticated user's account page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user()->load(['discordRoles', 'plannedAbsences.character']);

        return Inertia::render('Account/Index', [
            'roles' => $user->discordRoles
                ->where('is_visible', true)
                ->sortByDesc('position')
                ->values()
                ->map(fn ($role) => ['id' => $role->id, 'name' => $role->name]),
            'planned_absences' => PlannedAbsenceResource::collection($user->plannedAbsences),
        ]);
    }
}
