<?php

namespace App\Http\Controllers;

use App\Enums\AllianceRaces;
use App\Models\GuildRank;
use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\MediaService;
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
        protected MediaService $media,
    ) {
        $this->classes = collect(Cache::tags(['blizzard', 'mapped-response'])->remember('playable-classes.index', now()->addDays(30), function () {
            return collect(Arr::get($this->blizzard->getPlayableClasses(), 'classes'))->map(function (array $playableClass) {
                $media = $this->blizzard->getPlayableClassMedia(Arr::get($playableClass, 'id'));
                $assets = array_values($this->media->get(Arr::get($media, 'assets')));

                return array_merge($playableClass, ['media' => $assets[0] ?? null]);
            })->all();
        }));

        $this->races = collect(Cache::tags(['blizzard', 'mapped-response'])->remember('playable-races.alliance-races', now()->addDays(30), function () {
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
            'classes' => $this->classes->toArray(),
            'races' => $this->races->toArray(),
            'ranks' => $this->ranks,
            'level_cap' => 70,
            'members' => Inertia::defer(fn () => $this->buildMemberCollection()),
        ]);
    }

    protected function buildMemberCollection()
    {
        return Cache::tags(['blizzard', 'mapped-response'])->remember('guild-roster', now()->addHours(6), function () {
            $roster = $this->blizzard->getGuildRoster();

            return Arr::map(Arr::get($roster, 'members'), function (array $member, string $key) {
                data_set($member, 'character.playable_class', $this->classes->firstWhere('id', Arr::get($member, 'character.playable_class.id')));
                data_set($member, 'character.playable_race', $this->races->firstWhere('id', Arr::get($member, 'character.playable_race.id')));
                data_set($member, 'rank', $this->ranks->firstWhere('position', Arr::get($member, 'rank'))?->toArray());

                return $member;
            });
        });
    }
}
