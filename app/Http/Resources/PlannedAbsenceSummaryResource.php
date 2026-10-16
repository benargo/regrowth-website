<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A planned absence reduced to its reason's label and icon and its notes.
 */
class PlannedAbsenceSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{reason: array{label: string, icon: string}, notes: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'reason' => [
                'label' => $this->reason->label,
                'icon' => $this->reason->icon,
            ],
            'notes' => $this->notes,
        ];
    }
}
