<?php

namespace App\Services\Blizzard\Data;

use App\Models\GuildRank;
use App\Services\Blizzard\PlayableClassService;
use App\Services\Blizzard\PlayableRaceService;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class GuildMember
{
    public function __construct(
        public array $character,
        public GuildRank|int $rank,
    ) {}

    /**
     * @param  array{id: int, name: string, slug: string}  $data
     */
    public static function fromArray(array $data): self
    {
        $rank = GuildRank::select('id', 'position', 'name')->where('position', $data['rank'])->first() ?? $data['rank'];

        return new self(
            character: $data['character'],
            rank: $rank,
        );
    }

    /**
     * @return array{character: array, rank: int|array}
     */
    public function toArray(): array
    {
        return [
            'character' => [
                'id' => Arr::get($this->character, 'id'),
                'name' => Arr::get($this->character, 'name'),
                'level' => Arr::get($this->character, 'level'),
                'realm' => [
                    'id' => Arr::get($this->character, 'realm.id'),
                    'slug' => Arr::get($this->character, 'realm.slug'),
                ],
                'playable_class' => Arr::get($this->character, 'playable_class'),
                'playable_race' => Arr::get($this->character, 'playable_race'),
                'faction' => Str::ucfirst(Str::lower(Arr::get($this->character, 'faction.type'))),
            ],
            'rank' => $this->rank instanceof GuildRank ? [
                'id' => $this->rank->id,
                'position' => $this->rank->position,
                'name' => $this->rank->name,
            ] : $this->rank,
        ];
    }

    /**
     * Load relations for the guild member.
     */
    public function with(...$relations): static
    {
        foreach ($relations as $dotRelation) {
            data_set($this, $dotRelation, $this->loadRelation($dotRelation));
        }

        return $this;
    }

    /**
     * Load a relation for the guild member.
     */
    protected function loadRelation(string $relation): mixed
    {
        return match ($relation) {
            'character.playable_class' => app(PlayableClassService::class)->find(Arr::get($this->character, 'playable_class.id')),
            'character.playable_race' => app(PlayableRaceService::class)->find(Arr::get($this->character, 'playable_race.id')),
            'rank' => $this->rank = GuildRank::firstWhere('position', $this->rank),
        };
    }
}
