<?php

namespace App\Http\Resources;

use App\Contracts\HasCharacterMedia;
use App\Contracts\Http\Resources\ScopedResource;
use App\Http\Resources\Concerns\HasScopes;
use App\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform a character, optionally limited to the `attendance` or `race` scope.
 * `portrait_url` is only included when `media` is loaded.
 * `shadow_avatar_url` is only included when `playableRace` and its `media` are loaded.
 * `user` is only included when `user` is loaded; load `user.discordRoles` with it.
 */
class CharacterResource extends JsonResource implements ScopedResource
{
    use HasScopes;

    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = Character::class;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->scoped([
            'id' => $this->id,
            'full_name' => $this->full_name,
            'name' => $this->name,
            'surname' => $this->surname,
            'slug' => $this->slug,
            'level' => $this->level,
            'is_main' => $this->is_main,
            'is_loot_councillor' => $this->is_loot_councillor,
            'portrait_url' => $this->whenLoaded('media', fn () => $this->getFirstMediaUrl(HasCharacterMedia::MEDIA_COLLECTION) ?: null),
            'shadow_avatar_url' => $this->when(
                $this->relationLoaded('playableRace') && ($this->playableRace === null || $this->playableRace->relationLoaded('media')),
                fn (): ?string => $this->gender ? $this->playableRace?->shadowAvatarUrl($this->gender) : null,
            ),
            'planned_absences' => $this->whenLoaded('plannedAbsences', fn () => PlannedAbsenceResource::collection($this->plannedAbsences)),
            'playable_class' => $this->whenLoaded('playableClass', fn (): array => $this->scopeNested($this->playableClass->toResource())->resolve($request)),
            'playable_race' => $this->whenLoaded('playableRace', fn () => (new PlayableRaceResource($this->playableRace))->resolve($request)),
            'gender' => $this->gender,
            'pivot' => $this->whenPivotLoaded('pivot_characters_raid_reports', fn () => [
                'presence' => $this->pivot->presence,
                'is_loot_councillor' => $this->pivot->is_loot_councillor,
            ]),
            'rank' => $this->whenLoaded('rank', fn (): array => $this->scopeNested($this->rank->toResource())->resolve($request)),
            'specializations' => $this->whenLoaded('specializations', fn () => PlayableSpecializationResource::collection($this->specializations)->resolve($request)),
            // Recursive self-reference: never eager-load linkedCharacters.linkedCharacters
            // or this will infinite-loop. Use a separate summary resource for nested cases.
            'linked_characters' => $this->whenLoaded('linkedCharacters', fn () => CharacterResource::collection($this->linkedCharacters)->resolve($request)),
            'user' => $this->whenLoaded('user', fn () => $this->user?->toResource()),
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function scopeDefinitions(): array
    {
        return [
            'attendance' => ['full_name', 'id', 'name', 'playable_class', 'rank', 'surname'],
            'race' => ['full_name', 'id', 'level', 'name', 'playable_class', 'portrait_url', 'shadow_avatar_url', 'slug', 'surname'],
        ];
    }

    /**
     * @return array<string, array<class-string, string>>
     */
    protected function scopeMappings(): array
    {
        return [
            'attendance' => [GuildRankResource::class => 'attendance', PlayableClassResource::class => 'attendance'],
            'race' => [PlayableClassResource::class => 'attendance'],
        ];
    }
}
