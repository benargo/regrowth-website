<?php

namespace App\Http\Controllers;

use App\Enums\AllianceRaces;
use App\Models\GuildRank;
use App\Services\Blizzard\Data\GuildMember;
use App\Services\Blizzard\GuildService;
use App\Services\Blizzard\PlayableClassService;
use App\Services\Blizzard\PlayableRaceService;
use Illuminate\Support\Arr;
use Inertia\Inertia;

class GuildRosterController extends Controller
{
    protected PlayableClassService $classService;

    protected PlayableRaceService $raceService;

    public function index(
        PlayableClassService $classService,
        PlayableRaceService $raceService
    ) {
        $this->classService = $classService;
        $this->raceService = $raceService;

        $classes = collect(Arr::get($this->classService->index(), 'classes'))
            ->sortBy('name')
            ->values()
            ->toArray();

        $races = collect(Arr::get($this->raceService->index(), 'races'))
            ->filter(function ($race) {
                return in_array($race['id'], AllianceRaces::ids());
            })
            ->values()
            ->toArray();

        return Inertia::render('Roster', [
            'classes' => $classes,
            'races' => $races,
            'ranks' => GuildRank::orderBy('position')->get(),
            'level_cap' => 70,
            'members' => Inertia::defer(fn () => $this->buildMemberCollection()),
        ]);
    }

    protected function buildMemberCollection()
    {
        $guildService = app(GuildService::class);

        $roster = $guildService->roster();

        $members = collect($roster['members'])->map(function ($memberData) {
            $member = GuildMember::fromArray($memberData);

            data_set($member, 'character.playable_class', $this->classService->find(Arr::get($memberData, 'character.playable_class.id')));
            data_set($member, 'character.playable_class.media', $this->classService->media(Arr::get($memberData, 'character.playable_class.id')));
            data_set($member, 'character.playable_race', $this->raceService->find(Arr::get($memberData, 'character.playable_race.id')));

            return $member;
        });

        return $members->toArray();
    }
}
