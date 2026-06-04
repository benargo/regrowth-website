<?php

namespace App\Http\Controllers;

use App\Enums\AllianceRaces;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Data\Guild\GuildRosterMemberData;
use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use App\Http\Integrations\Blizzard\Requests\PlayableRace\GetPlayableRaceIndexRequest;
use App\Models\GuildRank;
use App\Models\PlayableClass;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class GuildRosterController extends Controller
{
    /** @var array<int, LinkData> */
    private array $races;

    /** @var Collection<int, GuildRank> */
    private Collection $ranks;

    public function __construct(
        protected BlizzardConnector $blizzard,
    ) {
        $this->races = array_filter(
            $this->blizzard->send(new GetPlayableRaceIndexRequest)->dto(),
            fn (LinkData $race) => in_array($race->id, AllianceRaces::ids(), true),
        );

        $this->ranks = GuildRank::select('id', 'position', 'name')->orderBy('position')->get();
    }

    public function __invoke(Request $request): Response
    {
        return Inertia::render('Roster', [
            'classes' => PlayableClass::all()->toResourceCollection()->toArray($request),
            'races' => array_values(array_map(fn (LinkData $race) => [
                'id' => $race->id,
                'name' => $race->name,
            ], $this->races)),
            'ranks' => $this->ranks,
            'level_cap' => 70,
            'members' => Inertia::defer(fn () => $this->buildMemberCollection($request)),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildMemberCollection(Request $request): array
    {
        $roster = $this->blizzard->send(new GetGuildRosterRequest(
            $this->blizzard->defaultRealmSlug(),
            $this->blizzard->defaultGuildSlug(),
        ))->dto();

        return collect($roster->members)
            ->map(function (GuildRosterMemberData $member) {
                return [
                    'character' => [
                        'id' => $member->character->id,
                        'name' => $member->character->name,
                        'level' => $member->character->level,
                        'playable_class' => PlayableClass::find($member->character->playableClass?->id),
                        'playable_race' => $member->character->playableRace?->only('id', 'name'),
                    ],
                    'rank' => $this->ranks->firstWhere('position', $member->rank)?->toArray(),
                ];
            })
            ->all();
    }
}
