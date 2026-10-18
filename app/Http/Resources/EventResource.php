<?php

namespace App\Http\Resources;

use App\Contracts\Http\Resources\ScopedResource;
use App\Enums\SignupStatus;
use App\Http\Resources\Concerns\HasScopes;
use App\Models\Boss;
use App\Models\Character;
use App\Models\EventAssignment;
use App\Models\Raid;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Transform an event, optionally limited to the `members`, `visitors` or `template` scope.
 *
 * Fields whose relation is not loaded are omitted:
 *   - assignments:  'assignments.group'
 *   - banner:       'raids.media'
 *   - composition:  'characters.rank' (and 'raids' for the party size)
 *   - raids:        'raids'; bosses need 'raids.bosses.media', 'bosses.media'
 *                   and 'assignments.group'
 *   - users:        'users'
 *   - channel:      attached with withChannel()
 */
class EventResource extends JsonResource implements ScopedResource
{
    use HasScopes;

    /**
     * @var array{id: string, name: string, position: int}|null
     */
    protected ?array $discordChannel = null;

    protected bool $hasDiscordChannel = false;

    /**
     * Attach the event's Discord channel, or null when it has none.
     *
     * @param  array{id: string, name: string, position: int}|null  $channel
     */
    public function withChannel(?array $channel): static
    {
        $this->discordChannel = $channel;
        $this->hasDiscordChannel = true;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<array-key, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->scoped([
            'id' => $this->id,
            'title' => $this->title,
            'start_time' => $this->start_time?->toIso8601String(),
            'end_time' => $this->end_time?->toIso8601String(),
            'duration' => $this->start_time && $this->end_time ? $this->duration : null,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'color' => $this->color,
            'banner_url' => $this->whenLoaded('raids', fn (): ?string => $this->bannerRaid()?->banner_url),
            'banner_position' => $this->whenLoaded('raids', fn (): ?string => $this->bannerRaid()?->banner_position),
            'assignments' => $this->whenLoaded('assignments', fn (): array => (new EventAssignmentsCollection(
                $this->assignments->whereNull('boss_id')->values()
            ))->resolve($request)),
            'composition' => $this->whenLoaded('characters', fn (): array => $this->buildComposition($request)),
            'raids' => $this->whenLoaded('raids', fn (): array => $this->buildRaids($request)),
            'users' => $this->whenLoaded('users', fn () => $this->users->toResourceCollection()),
            $this->mergeWhen($this->hasDiscordChannel, fn (): array => ['channel' => $this->discordChannel]),
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function scopeDefinitions(): array
    {
        return [
            'members' => ['id', 'title', 'start_time', 'end_time', 'duration', 'raids', 'channel'],
            'template' => ['id', 'title', 'updated_at', 'raids'],
            'visitors' => ['id', 'title', 'start_time', 'end_time', 'raids'],
        ];
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
     * The first of the event's raids, in pivot order, that has a banner.
     */
    private function bannerRaid(): ?Raid
    {
        return $this->raids->first(fn (Raid $raid): bool => $raid->banner_url !== null);
    }

    /**
     * The event's raids. Each raid's bosses are included when loaded.
     *
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     max_players: int,
     *     sort_order: int,
     *     bosses?: array<int, array<string, mixed>>
     * }>
     */
    protected function buildRaids(Request $request): array
    {
        return $this->raids->map(fn (Raid $raid) => [
            'id' => $raid->id,
            'name' => $raid->name,
            'slug' => $raid->slug,
            'max_players' => $raid->max_players,
            'sort_order' => $raid->pivot->sort_order,
            'bosses' => $this->when($raid->relationLoaded('bosses'), fn (): array => $this->buildBosses($raid, $request)),
        ])->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildBosses(Raid $raid, Request $request): array
    {
        $visibleBossIds = $this->bosses->pluck('id')->flip();
        $bossByIdAssignments = $this->assignments->whereNotNull('boss_id')->groupBy('boss_id');

        return $raid->bosses
            ->map(fn (Boss $boss) => $this->buildBoss($boss, $bossByIdAssignments, $visibleBossIds, $request))
            ->values()
            ->all();
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
