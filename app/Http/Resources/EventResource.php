<?php

namespace App\Http\Resources;

use App\Actions\Discord\FetchChannel;
use App\Enums\SignupStatus;
use App\Models\Boss;
use App\Models\Character;
use App\Models\Event;
use App\Models\EventAssignment;
use App\Models\Raid;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Transform an event into one of three scoped shapes: full (default),
 * members, or visitors.
 *
 * Relations each scope expects to be loaded:
 *   - default:      $event->load('raids.bosses.media', 'bosses.media', 'assignments.group', 'characters.rank')
 *                   and optionally 'users'
 *   - forMembers:   nothing
 *   - forVisitors:  $event->load('raids')
 *
 * Each boss under raids.bosses has an is_visible flag, true when the boss is
 * attached to the event through the bosses relation.
 */
class EventResource extends JsonResource
{
    protected const SCOPE_FULL = 'full';

    protected const SCOPE_MEMBERS = 'members';

    protected const SCOPE_VISITORS = 'visitors';

    protected string $scope = self::SCOPE_FULL;

    /**
     * Create a resource with the members shape: id, title, start and end
     * times, duration, and the Discord channel.
     */
    public static function forMembers(mixed $resource): static
    {
        return static::withScope($resource, self::SCOPE_MEMBERS);
    }

    /**
     * Create a resource with the visitors shape: id, title, start and end
     * times, and raid names.
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
            $data['users'] = $this->whenLoaded('users', fn () => $this->users->toResourceCollection());
        }

        try {
            $data['channel'] = $this->channel_id
                ? FetchChannel::run($this->channel_id)->only('id', 'name', 'position')->toArray()
                : null;
        } catch (\Exception $e) {
            report($e);
        }

        return $data;
    }

    /** @return array{groups: array<int, array<string, mixed>>, bench: array<int, array<string, mixed>>} */
    protected function buildComposition(Request $request): array
    {
        return [
            'groups' => $this->buildGroups($request),
            'bench' => $this->buildBench($request),
        ];
    }

    /**
     * @return array<int, array{
     *     group_number: int,
     *     is_team: bool,
     *     characters: array<int, array{
     *         id: int,
     *         name: string,
     *         playable_class: array<string, mixed>|null,
     *         rank: array{name: ?string, sort_order: ?int},
     *         slot_number: int,
     *         signup_status: SignupStatus,
     *         is_leader: bool,
     *         is_loot_councillor: bool,
     *         is_loot_master: bool
     *     }>
     * }>
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
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     playable_class: array<string, mixed>|null,
     *     rank: array{name: ?string, sort_order: ?int}
     * }>
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
     * @param  Collection<int, Collection<int, EventAssignment>>  $bossByIdAssignments
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     max_players: int,
     *     sort_order: int,
     *     bosses: array<int, array<string, mixed>>
     * }>
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
     * @param  Collection<int, Collection<int, EventAssignment>>  $bossByIdAssignments
     * @param  Collection<int, int>  $visibleBossIds  Boss ids attached to the event via the pivot.
     * @return array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     sort_order: int,
     *     images: array<int, string>,
     *     notes: ?string,
     *     is_visible: bool,
     *     assignments: array<int, mixed>
     * }
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
