<?php

namespace App\Http\Resources;

use App\Contracts\HasCharacterMedia;
use App\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Eager-loading requirements differ per scope:
 *   - default:        whichever optional relations the consumer needs
 *   - forAbsenceList: $character->load('media', 'playableClass')
 */
class CharacterResource extends JsonResource
{
    protected const SCOPE_DEFAULT = 'default';

    protected const SCOPE_ABSENCE_LIST = 'absence_list';

    protected string $scope = self::SCOPE_DEFAULT;

    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = Character::class;

    /**
     * The narrow shape used by the officer planned absences table.
     */
    public static function forAbsenceList(mixed $resource): static
    {
        return static::withScope($resource, self::SCOPE_ABSENCE_LIST);
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
        if ($this->scope === self::SCOPE_ABSENCE_LIST) {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'slug' => $this->slug,
                'portrait_url' => $this->getFirstMediaUrl(HasCharacterMedia::MEDIA_COLLECTION) ?: null,
                'playable_class' => $this->whenLoaded('playableClass', fn () => (new PlayableClassResource($this->playableClass))->resolve($request)),
            ];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'level' => $this->level,
            'is_main' => $this->is_main,
            'is_loot_councillor' => $this->is_loot_councillor,
            'portrait_url' => $this->getFirstMediaUrl(HasCharacterMedia::MEDIA_COLLECTION) ?: null,
            'planned_absences' => $this->whenLoaded('plannedAbsences', fn () => PlannedAbsenceResource::collection($this->plannedAbsences)),
            'playable_class' => $this->whenLoaded('playableClass', fn () => (new PlayableClassResource($this->playableClass))->resolve($request)),
            'playable_race' => $this->whenLoaded('playableRace', fn () => (new PlayableRaceResource($this->playableRace))->resolve($request)),
            'gender' => $this->gender,
            'pivot' => $this->whenPivotLoaded('pivot_characters_raid_reports', fn () => [
                'presence' => $this->pivot->presence,
                'is_loot_councillor' => $this->pivot->is_loot_councillor,
            ]),
            'rank' => $this->whenLoaded('rank', fn () => (new GuildRankResource($this->rank))->resolve($request)),
            'specializations' => $this->whenLoaded('specializations', fn () => PlayableSpecializationResource::collection($this->specializations)->resolve($request)),
            // Recursive self-reference: never eager-load linkedCharacters.linkedCharacters
            // or this will infinite-loop. Use a separate summary resource for nested cases.
            'linked_characters' => $this->whenLoaded('linkedCharacters', fn () => CharacterResource::collection($this->linkedCharacters)->resolve($request)),
        ];
    }
}
