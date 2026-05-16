<?php

namespace App\Http\Controllers;

use App\Enums\AllianceRaces;
use App\Http\Resources\PlayableClassResource;
use App\Models\GuildRank;
use App\Models\PlayableClass;
use App\Services\Blizzard\BlizzardService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class GuildRosterController extends Controller
{
    private $classes;

    private $races;

    private $ranks;

    public function __construct(
        protected BlizzardService $blizzard,
    ) {
        // Load from DB keyed by ID; icons are managed by PlayableClassSeeder via the media library.
        // Keyed by ID so buildMemberCollection can look up by class ID in O(1).
        $this->classes = collect(PlayableClassResource::collection(PlayableClass::all())->toArray(request()))
            ->keyBy('id');

        $this->races = collect(Cache::tags(['blizzard', 'mapped-response'])->remember('playable_races:alliance_races', now()->addDays(30), function () {
            return collect(Arr::get($this->blizzard->getPlayableRaces(), 'races', []))
                ->filter(fn (array $race) => in_array($race['id'], AllianceRaces::ids()))
                ->values()
                ->all();
        }));

        $this->ranks = GuildRank::select('id', 'position', 'name')->orderBy('position')->get();
    }

    public function index()
    {
        return Inertia::render('Roster', [
            'classes' => $this->classes->values()->all(),
            'races' => $this->races->toArray(),
            'ranks' => $this->ranks,
            'level_cap' => 70,
            'members' => Inertia::defer(fn () => $this->buildMemberCollection()),
        ]);
    }

    protected function buildMemberCollection()
    {
        return Cache::tags(['blizzard', 'mapped-response'])->remember('guild_roster', now()->addHours(6), function () {
            $roster = $this->blizzard->getGuildRoster();

            return Arr::map(Arr::get($roster, 'members'), function (array $member) {
                $classId = Arr::get($member, 'character.playable_class.id');
                $raceId = Arr::get($member, 'character.playable_race.id');

                data_set($member, 'character.playable_class', $classId ? $this->classes->get($classId) : null);
                data_set($member, 'character.playable_race', $this->races->firstWhere('id', $raceId));
                data_set($member, 'rank', $this->ranks->firstWhere('position', Arr::get($member, 'rank'))?->toArray());

                return $member;
            });
        });
    }
}
