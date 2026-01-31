<?php

namespace App\Http\Controllers;

use App\Models\GuildRank;
use App\Services\Blizzard\Data\GuildMember;
use App\Services\Blizzard\GuildService;
use App\Services\Blizzard\PlayableClassService;
use App\Services\Blizzard\PlayableRaceService;
use Illuminate\Support\Arr;
use Inertia\Inertia;

class GuildRosterController extends Controller
{
    public function index(
        GuildService $guildService,
        PlayableClassService $classService,
        PlayableRaceService $raceService
    ) {
        $roster = $guildService->fresh()->roster();

        $members = collect($roster['members'])->map(function ($memberData) use ($classService, $raceService) {
            $member = GuildMember::fromArray($memberData)->with('rank');

            data_set($member, 'character.playable_class', $classService->find(Arr::get($memberData, 'character.playable_class.id')));
            data_set($member, 'character.playable_class.media', $classService->media(Arr::get($memberData, 'character.playable_class.id')));
            data_set($member, 'character.playable_race', $raceService->find(Arr::get($memberData, 'character.playable_race.id')));

            return $member;
        });

        return Inertia::render('Roster', [
            'members' => $members->toArray(),
            'classes' => $classService->index(),
            'races' => $raceService->index(),
            'ranks' => GuildRank::orderBy('position')->get(),
            'level_cap' => 70,
        ]);
    }
}
