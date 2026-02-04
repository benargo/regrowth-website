<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\DiscordRole;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Dashboard/Index', [
            'discordRoles' => [
                'raider' => DiscordRole::where('name', 'Raider')->value('id'),
                'member' => DiscordRole::where('name', 'Member')->value('id'),
                'guest' => DiscordRole::where('name', 'Guest')->value('id'),
            ],
        ]);
    }
}
