<?php

namespace App\Http\Resources;

use App\Enums\RaidBackground;
use App\Models\Boss;
use App\Models\Character;
use App\Models\Raid;
use App\Services\RaidHelper\RaidHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Requires raids.bosses.media, assignments, and characters.rank to be eager-loaded
 * before construction. Use $event->load('raids.bosses.media', 'assignments', 'characters.rank').
 */
class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $allAssignments = $this->assignments;
        $eventAssignments = $allAssignments->whereNull('boss_id')->values();
        $bossByIdAssignments = $allAssignments->whereNotNull('boss_id')->groupBy('boss_id');

        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'start_time' => $this->start_time->toIso8601String(),
            'end_time' => $this->end_time?->toIso8601String(),
            'duration' => $this->duration,
            'background' => $this->buildBackground(),
            'assignments' => (new EventAssignmentsCollection($eventAssignments))->resolve($request),
            'composition' => $this->buildComposition($request),
            'raids' => $this->buildRaids($bossByIdAssignments, $request),
        ];

        try {
            $data['channel'] = $this->channel->only('id', 'name', 'position')->toArray();
        } catch (\Exception $e) {
            // Discord API unavailable — omit channel
        }

        return $data;
    }

    /**
     * @return array{groups: array<int, mixed>, bench: array<int, mixed>}
     */
    private function buildComposition(Request $request): array
    {
        return [
            'groups' => $this->buildGroups(),
            'bench' => $this->buildBench($request),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function buildGroups(): array
    {
        $maxPlayers = $this->raids->max('max_players') ?? 0;
        $maxSlot = $this->characters->max(fn (Character $c) => $c->pivot->slot_number) ?? 0;
        $maxGroups = $maxSlot > 0 ? (int) ceil($maxPlayers / $maxSlot) : 0;
        $isTeam = $maxSlot > 5;

        return $this->characters
            ->sortBy([
                fn (Character $a, Character $b) => $a->pivot->group_number <=> $b->pivot->group_number,
                fn (Character $a, Character $b) => $a->pivot->slot_number <=> $b->pivot->slot_number,
            ])
            ->groupBy(fn (Character $c) => $c->pivot->group_number)
            ->filter(fn ($group, $groupNumber) => $isTeam || $groupNumber <= $maxGroups)
            ->map(fn ($groupCharacters, $groupNumber) => [
                'group_number' => $groupNumber,
                'is_team' => $isTeam,
                'characters' => $groupCharacters->map(fn (Character $character) => [
                    'id' => $character->id,
                    'name' => $character->name,
                    'playable_class' => $character->playable_class,
                    'rank' => [
                        'name' => $character->rank?->name,
                        'position' => $character->rank?->position,
                    ],
                    'slot_number' => $character->pivot->slot_number,
                    'is_confirmed' => $character->pivot->is_confirmed,
                    'is_leader' => $character->pivot->is_leader,
                    'is_loot_councillor' => $character->pivot->is_loot_councillor,
                    'is_loot_master' => $character->pivot->is_loot_master,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    private function buildBackground(): ?string
    {
        $raidId = $this->raids->pluck('id')->first();

        if (empty($raidId)) {
            return null;
        }

        return RaidBackground::fromRaidId($raidId)->value;
    }

    /**
     * @return array<int, mixed>
     */
    private function buildBench(Request $request): array
    {
        if (! $this->raid_helper_event_id || $this->characters->isEmpty()) {
            return [];
        }

        $compNames = $this->characters->pluck('name');

        $benchedCharacters = Character::hydrate(
            Cache::tags(['events'])->remember(
                "events:{$this->raid_helper_event_id}:benched",
                now()->addMinutes(10),
                function () use ($compNames) {
                    $raidHelper = app(RaidHelper::class);
                    $rhEvent = $raidHelper->getEvent((int) $this->raid_helper_event_id);

                    $signUpNames = collect($rhEvent->signUps)->pluck('name');
                    $benchedNames = $signUpNames->diff($compNames);

                    return Character::whereIn('name', $benchedNames)
                        ->get()
                        ->toArray();
                }
            )
        )->load('rank');

        return $benchedCharacters->map(fn (Character $character) => [
            'id' => $character->id,
            'name' => $character->name,
            'playable_class' => $character->playable_class,
            'rank' => [
                'name' => $character->rank?->name,
                'position' => $character->rank?->position,
            ],
        ])->values()->all();
    }

    /**
     * @param  Collection<int|string, mixed>  $bossByIdAssignments
     * @return array<int, mixed>
     */
    private function buildRaids(Collection $bossByIdAssignments, Request $request): array
    {
        return $this->raids->map(fn (Raid $raid) => [
            'name' => $raid->name,
            'slug' => $raid->slug,
            'max_players' => $raid->max_players,
            'bosses' => $raid->bosses->map(fn (Boss $boss) => [
                'id' => $boss->id,
                'name' => $boss->name,
                'slug' => $boss->slug,
                'encounter_order' => $boss->encounter_order,
                'images' => $boss->getMedia()->map->getUrl()->values()->all(),
                'notes' => $boss->notes,
                'assignments' => (new EventAssignmentsCollection(
                    $bossByIdAssignments->get($boss->id, collect())
                ))->resolve($request),
            ])->values()->all(),
        ])->values()->all();
    }
}
