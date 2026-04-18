<?php

namespace App\Http\Resources\WarcraftLogs;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportClusterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'reports' => LinkedReportResource::collection($this->resource['reports']),
        ];
    }
}
