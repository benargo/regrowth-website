<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\DiscordRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Dashboard/Index', [
            'discordRoles' => [
                'raider' => DiscordRole::Raider->value,
                'member' => DiscordRole::Member->value,
                'guest' => DiscordRole::Guest->value,
            ],
        ]);
    }
}
