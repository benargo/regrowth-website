<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceScatterPointResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'character' => $this->character->load('playableClass')->toResource(CharacterSummaryResource::class)->resolve($request),
            'percentage' => $this->percentage,
            'raidsTotal' => count(array_filter($this->attendance, fn ($v) => $v !== null)),
            'raidsAttended' => count(array_filter($this->attendance, fn ($v) => $v === 1)),
            'benched' => count(array_filter($this->attendance, fn ($v) => $v === 2)),
            'plannedAbsences' => count(array_filter($this->plannedAbsences, fn ($v) => $v !== null)),
            'otherAbsences' => count(array_filter($this->attendance, fn ($v) => $v === 0)),
        ];
    }
}
