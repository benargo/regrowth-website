<?php

namespace App\Http\Resources;

use App\Models\PlannedAbsence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Eager-loading requirements differ per scope:
 *   - default:        nothing (character.playableClass is lazy-loaded)
 *   - forOfficerList: $absence->load('character.media', 'character.playableClass', 'createdBy');
 */
class PlannedAbsenceResource extends JsonResource
{
    protected const SCOPE_DEFAULT = 'default';

    protected const SCOPE_OFFICER_LIST = 'officer_list';

    protected string $scope = self::SCOPE_DEFAULT;

    /**
     * The officer index table shape, embedding the character with its portrait.
     */
    public static function forOfficerList(mixed $resource): static
    {
        return static::withScope($resource, self::SCOPE_OFFICER_LIST);
    }

    /**
     * @param  iterable<int, PlannedAbsence>  $resources
     */
    public static function collectionForOfficerList(iterable $resources): AnonymousResourceCollection
    {
        return static::collection(collect($resources)->map(fn (PlannedAbsence $absence) => static::forOfficerList($absence)));
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
        return [
            'id' => $this->id,
            'character' => $this->whenLoaded('character', function () use ($request) {
                if ($this->scope === self::SCOPE_OFFICER_LIST) {
                    return CharacterResource::forAbsenceList($this->character)->resolve($request);
                }

                return $this->character
                    ->load('playableClass')
                    ->toResource(CharacterSummaryResource::class)
                    ->resolve($request);
            }),
            'user' => $this->whenLoaded('user', function () use ($request) {
                return $this->user->toResource()->resolve($request);
            }),
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'reason' => $this->reason,
            'notes' => $this->notes,
            'created_by' => $this->whenLoaded('createdBy', function () use ($request) {
                return $this->createdBy->toResource()->resolve($request);
            }),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'deleted_at' => $this->deleted_at?->toDateTimeString(),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array{permissions: array{edit: bool, delete: bool, restore: bool, backdate: bool}}
     */
    public function with(Request $request): array
    {
        $user = $request->user();

        return [
            'permissions' => [
                'edit' => $user?->can('update', $this->resource) ?? false,
                'delete' => $user?->can('delete', $this->resource) ?? false,
                'restore' => $user?->can('restore', $this->resource) ?? false,
                'backdate' => $user?->can('backdate', PlannedAbsence::class) ?? false,
            ],
        ];
    }
}
