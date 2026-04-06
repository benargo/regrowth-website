<?php

namespace App\Http\Controllers;

use App\Enums\AllianceRaces;
use App\Models\GuildRank;
use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\Data\GuildMember;
use Illuminate\Support\Arr;
use Inertia\Inertia;

class GuildRosterController extends Controller
{
    protected BlizzardService $blizzard;

    public function index(BlizzardService $blizzard)
    {
        $this->blizzard = $blizzard;

        $classes = collect(Arr::get($blizzard->getPlayableClasses(), 'classes'))
            ->sortBy('name')
            ->values()
            ->toArray();

        $races = collect(Arr::get($blizzard->getPlayableRaces(), 'races'))
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
        $roster = $this->blizzard->getGuildRoster();

        $members = collect($roster['members'])->map(function ($memberData) {
            $member = GuildMember::fromArray($memberData);

            data_set($member, 'character.playable_class', $this->blizzard->findPlayableClass(Arr::get($memberData, 'character.playable_class.id')));
            data_set($member, 'character.playable_class.media', $this->blizzard->getPlayableClassMedia(Arr::get($memberData, 'character.playable_class.id')));
            data_set($member, 'character.playable_race', $this->blizzard->findPlayableRace(Arr::get($memberData, 'character.playable_race.id')));

            return $member;
        });

        return $members->toArray();
    }
}
