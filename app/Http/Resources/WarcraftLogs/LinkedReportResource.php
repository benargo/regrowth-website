<?php

namespace App\Http\Resources\WarcraftLogs;

use App\Http\Resources\UserResource;
use App\Models\Raids\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LinkedReportResource extends JsonResource
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = Report::class;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'zone' => [
                'id' => $this->zone_id,
                'name' => $this->zone_name,
            ],
            'pivot' => $this->whenPivotLoaded('raid_report_links', fn () => [
                'created_by' => $this->pivot->creator ? new UserResource($this->pivot->creator) : null,
                'created_at' => $this->pivot->created_at,
                'updated_at' => $this->pivot->updated_at,
            ]),
        ];
    }
}
