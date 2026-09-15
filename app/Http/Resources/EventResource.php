<?php

namespace App\Http\Resources;

use App\Models\Boss;
use App\Models\Character;
use App\Models\Event;
use App\Models\Raid;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * A scoped view of an event. The scope is chosen by audience, not by detail
 * level — which is why forMembers() may attach Discord channel data and
 * forVisitors() may not.
 *
 * Eager-loading requirements differ per scope:
 *   - default:      $event->load('raids.bosses.media', 'bosses.media', 'assignments.group', 'characters.rank')
 *   - forMembers:   nothing
 *   - forVisitors:  $event->load('raids')
 *
 * The bosses relation (the event's own pivot selection) drives the is_visible flag
 * on each boss nested under raids.bosses rather than a top-level key.
 */
class EventResource extends JsonResource
{
    protected const SCOPE_FULL = 'full';

    protected const SCOPE_MEMBERS = 'members';

    protected const SCOPE_VISITORS = 'visitors';

    protected string $scope = self::SCOPE_FULL;

    /**
     * The dashboard list shape for authenticated members. Includes Discord
     * channel data, which anonymous visitors must never receive.
     */
    public static function forMembers(mixed $resource): static
    {
        return static::withScope($resource, self::SCOPE_MEMBERS);
    }

    /**
     * The minimal public-safe shape for anonymous visitors. Makes no Discord
     * API call. Requires the raids relation: $event->load('raids').
     */
    public static function forVisitors(mixed $resource): static
    {
        return static::withScope($resource, self::SCOPE_VISITORS);
    }

    /**
     * @param  iterable<int, Event>  $resources
     * @return Collection<int, static>
     */
    public static function collectionForMembers(iterable $resources): Collection
    {
        return collect($resources)->map(fn ($event) => static::forMembers($event));
    }

    /**
     * @param  iterable<int, Event>  $resources
     * @return Collection<int, static>
     */
    public static function collectionForVisitors(iterable $resources): Collection
    {
        return collect($resources)->map(fn ($event) => static::forVisitors($event));
    }

    protected static function withScope(mixed $resource, string $scope): static
    {
        $instance = new static($resource);
        $instance->scope = $scope;

        return $instance;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'start_time' => $this->start_time?->toIso8601String(),
            'end_time' => $this->end_time?->toIso8601String(),
        ];

        if ($this->scope === self::SCOPE_VISITORS) {
            $data['raids'] = $this->raids->pluck('name')->values()->all();

            return $data;
        }

        $data['duration'] = $this->start_time && $this->end_time ? $this->duration : null;

        if ($this->scope === self::SCOPE_FULL) {
            $allAssignments = $this->assignments;
            $eventAssignments = $allAssignments->whereNull('boss_id')->values();
            $bossByIdAssignments = $allAssignments->whereNotNull('boss_id')->groupBy('boss_id');

            $data['color'] = $this->color;
            $data['background'] = $this->background_css_class?->value;
            $data['assignments'] = (new EventAssignmentsCollection($eventAssignments))->resolve($request);
            $data['composition'] = $this->buildComposition($request);
            $data['raids'] = $this->buildRaids($bossByIdAssignments, $request);
        }

        try {
            $data['channel'] = $this->channel?->only('id', 'name', 'position')->toArray();
        } catch (\Exception $e) {
            // Discord API unavailable — omit channel
        }

        return $data;
    }

    /**
     * @return array{groups: array<int, mixed>, bench: array<int, mixed>}
     */
    protected function buildComposition(Request $request): array
    {
        return [
            'groups' => $this->buildGroups($request),
            'bench' => $this->buildBench($request),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function buildGroups(Request $request): array
    {
        $maxPlayers = $this->raids->max('max_players') ?? 0;
        $maxSlot = $this->characters->max(fn (Character $c) => $c->pivot->slot_number) ?? 0;
        $maxGroups = $maxSlot > 0 ? (int) ceil($maxPlayers / $maxSlot) : 0;
        $isTeam = $maxSlot > 5;

        return $this->characters
            ->reject(fn (Character $c) => $c->pivot->is_benched)
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
                    'playable_class' => $character->playableClass()->first()?->toResource()->resolve($request),
                    'rank' => [
                        'name' => $character->rank?->name,
                        'sort_order' => $character->rank?->sort_order,
                    ],
                    'slot_number' => $character->pivot->slot_number,
                    'signup_status' => $character->pivot->signup_status,
                    'is_leader' => $character->pivot->is_leader,
                    'is_loot_councillor' => $character->pivot->is_loot_councillor,
                    'is_loot_master' => $character->pivot->is_loot_master,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, mixed>
     */
    protected function buildBench(Request $request): array
    {
        return $this->characters
            ->filter(fn (Character $c) => $c->pivot->is_benched)
            ->map(fn (Character $character) => [
                'id' => $character->id,
                'name' => $character->name,
                'playable_class' => $character->playableClass()->first()?->toResource()->resolve($request),
                'rank' => [
                    'name' => $character->rank?->name,
                    'sort_order' => $character->rank?->sort_order,
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int|string, mixed>  $bossByIdAssignments
     * @return array<int, mixed>
     */
    protected function buildRaids(Collection $bossByIdAssignments, Request $request): array
    {
        $visibleBossIds = $this->bosses->pluck('id')->flip();

        return $this->raids->map(fn (Raid $raid) => [
            'id' => $raid->id,
            'name' => $raid->name,
            'slug' => $raid->slug,
            'max_players' => $raid->max_players,
            'sort_order' => $raid->pivot->sort_order,
            'bosses' => $raid->bosses
                ->map(fn (Boss $boss) => $this->buildBoss($boss, $bossByIdAssignments, $visibleBossIds, $request))
                ->values()
                ->all(),
        ])->values()->all();
    }

    /**
     * @param  Collection<int|string, mixed>  $bossByIdAssignments
     * @param  Collection<int|string, int>  $visibleBossIds  Boss ids attached to the event via the pivot.
     * @return array<string, mixed>
     */
    protected function buildBoss(Boss $boss, Collection $bossByIdAssignments, Collection $visibleBossIds, Request $request): array
    {
        return [
            'id' => $boss->id,
            'name' => $boss->name,
            'slug' => $boss->slug,
            'sort_order' => $boss->sort_order,
            'images' => $boss->getMedia()->map->getUrl()->values()->all(),
            'notes' => $boss->notes,
            'is_visible' => $visibleBossIds->has($boss->id),
            'assignments' => (new EventAssignmentsCollection(
                $bossByIdAssignments->get($boss->id, collect())
            ))->resolve($request),
        ];
    }
}
