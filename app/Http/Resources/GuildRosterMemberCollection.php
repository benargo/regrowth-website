<?php

namespace App\Http\Resources;

use App\Http\Integrations\Blizzard\Data\Guild\GuildRosterMemberData;
use App\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** @property-read Collection<int, GuildRosterMemberData> $collection */
class GuildRosterMemberCollection extends ResourceCollection
{
    public static $wrap = null;

    /** @var Collection<int, Character> */
    private Collection $knownCharacters;

    /** @param  list<GuildRosterMemberData>  $resource */
    public function __construct(array $resource)
    {
        parent::__construct(collect($resource));

        $ids = $this->collection->pluck('character.id');

        $this->knownCharacters = Character::whereIn('id', $ids)
            ->with('specializations')
            ->get()
            ->keyBy('id');
    }

    /** @return array<int, array<string, mixed>> */
    public function toArray(Request $request): array
    {
        return $this->collection
            ->sortBy([
                fn (GuildRosterMemberData $a, GuildRosterMemberData $b) => $a->rank <=> $b->rank,
                fn (GuildRosterMemberData $a, GuildRosterMemberData $b) => $b->character->level <=> $a->character->level,
                fn (GuildRosterMemberData $a, GuildRosterMemberData $b) => $a->character->name <=> $b->character->name,
            ])
            ->map(function (GuildRosterMemberData $member) use ($request) {
                $character = $this->knownCharacters->get($member->character->id);

                return [
                    'character' => [
                        'id' => $member->character->id,
                        'name' => $member->character->name,
                        'slug' => Str::slug($member->character->name),
                        'level' => $member->character->level,
                        'playable_class_id' => $member->character->playableClass->id,
                        'playable_race_id' => $member->character->playableRace->id,
                        'is_known' => $character !== null,
                        'specializations' => $character !== null
                            ? PlayableSpecializationResource::collection($character->specializations)->resolve($request)
                            : [],
                    ],
                    'rank' => $member->rank,
                ];
            })->values()->all();
    }
}
