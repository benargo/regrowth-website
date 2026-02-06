<?php

namespace App\Http\Controllers;

use App\Services\Blizzard\GuildService;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function home(GuildService $guildService)
    {
        $members = $guildService->members()
            ->groupBy(fn ($member) => $member->character['level'])
            ->sortKeysDesc()
            ->take(3)
            ->map(fn ($group) => $group->sortBy(fn ($member) => $member->character['name'])->values())
            ->map(fn ($group) => $group->map(fn ($member) => $member->toArray()));

        return Inertia::render('Home', [
            'members' => $members,
        ]);
    }
}
