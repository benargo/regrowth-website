<?php

namespace App\Http\Resources;

use App\Attributes\Http\Resources\ScopeDefinition;
use App\Contracts\Http\Resources\ScopedResource;
use App\Http\Resources\Concerns\HasScopes;
use App\Models\GuildRank;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Transform a guild rank, optionally limited to the `attendance` scope.
 */
class GuildRankResource extends JsonResource implements ScopedResource
{
    use HasScopes;

    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = GuildRank::class;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->scoped([
            'id' => $this->id,
            'sort_order' => $this->sort_order,
            'name' => $this->name,
            'slug' => Str::slug($this->name),
            'count_attendance' => $this->count_attendance,
            'characters_count' => $this->whenCounted('characters'),
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function scopeDefinitions(): array
    {
        return ['attendance' => $this->attendance()];
    }

    /**
     * Name and slug.
     *
     * @return list<string>
     */
    #[ScopeDefinition]
    protected function attendance(): array
    {
        return ['name', 'slug'];
    }
}
