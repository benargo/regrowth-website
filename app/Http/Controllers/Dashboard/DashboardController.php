<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\DiscordRole;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

#[Authorize('view-officer-dashboard')]
class DashboardController extends Controller
{
    /**
     * Render the main dashboard page.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Manage/Dashboard', [
            'discordRoles' => DiscordRole::visible()->orderByDesc('position')->get(['id', 'name']),
        ]);
    }
}
