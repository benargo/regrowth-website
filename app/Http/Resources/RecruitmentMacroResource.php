<?php

namespace App\Http\Resources;

use App\Contracts\Models\TrashesRecruitmentMacros;
use App\Models\RecruitmentMacro;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A recruitment macro with its character count and limit. The `meta` block carries whether the
 * requesting user can delete or restore it and, for a trashed macro, the days until it is purged.
 *
 * @mixin RecruitmentMacro
 */
class RecruitmentMacroResource extends JsonResource implements TrashesRecruitmentMacros
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'type_label' => $this->type->label(),
            'content' => $this->content,
            'character_count' => $this->type->countCharacters($this->content),
            'character_limit' => $this->type->characterLimit(),
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->toResource()),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'deleted_at' => $this->deleted_at?->toDateTimeString(),
            'meta' => [
                'can_delete' => ! $this->trashed() && ($user?->can('delete', $this->resource) ?? false),
                'can_restore' => $this->trashed() && ($user?->can('restore', $this->resource) ?? false),
                'days_remaining' => $this->trashed()
                    ? max(0, (int) ceil(now()->diffInDays($this->deleted_at->copy()->addDays(self::TRASH_DAYS))))
                    : null,
            ],
        ];
    }
}
