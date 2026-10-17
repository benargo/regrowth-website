<?php

namespace App\Http\Resources;

use App\Attributes\Http\Resources\ScopeDefinition;
use App\Contracts\Http\Resources\ScopedResource;
use App\Http\Resources\Concerns\HasScopes;
use App\Models\PlannedAbsence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform a planned absence, optionally limited to the `attendance` scope.
 * Load `character.media` to include the character's portrait URL.
 *
 * The `meta` block carries `has_started` and `has_ended`, evaluated against the server's date.
 */
class PlannedAbsenceResource extends JsonResource implements ScopedResource
{
    use HasScopes;

    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = PlannedAbsence::class;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->scoped([
            'id' => $this->id,
            'character' => $this->whenLoaded('character', function () use ($request) {
                return $this->character
                    ->loadMissing('playableClass')
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
            'meta' => [
                'has_started' => $this->start_date->lte(today()),
                'has_ended' => $this->end_date?->lt(today()) ?? false,
            ],
        ]);
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

    /**
     * @return array<string, list<string>>
     */
    protected function scopeDefinitions(): array
    {
        return ['attendance' => $this->attendance()];
    }

    /**
     * Reason and notes.
     *
     * @return list<string>
     */
    #[ScopeDefinition]
    protected function attendance(): array
    {
        return ['reason', 'notes'];
    }
}
