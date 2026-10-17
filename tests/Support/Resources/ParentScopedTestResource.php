<?php

namespace Tests\Support\Resources;

use App\Attributes\Http\Resources\ScopeDefinition;
use App\Contracts\Http\Resources\ScopedResource;
use App\Http\Resources\Concerns\HasScopes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Array-backed resource nesting `ScopedTestResource` once and as a
 * collection, with `listing` and `full` scopes. `listing` maps nested
 * resources to `summary`.
 *
 * @property array{id: int, child: array<string, mixed>, children: list<array<string, mixed>>} $resource
 */
class ParentScopedTestResource extends JsonResource implements ScopedResource
{
    use HasScopes;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->scoped([
            'id' => $this->resource['id'],
            'child' => new ScopedTestResource($this->resource['child']),
            'children' => ScopedTestResource::collection($this->resource['children']),
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function scopeDefinitions(): array
    {
        return ['listing' => $this->listing(), 'full' => $this->full()];
    }

    /**
     * @return array<string, array<class-string, string>>
     */
    protected function scopeMappings(): array
    {
        return ['listing' => [ScopedTestResource::class => 'summary']];
    }

    /**
     * @return list<string>
     */
    #[ScopeDefinition]
    protected function listing(): array
    {
        return ['id', 'child', 'children'];
    }

    /**
     * @return list<string>
     */
    #[ScopeDefinition]
    protected function full(): array
    {
        return ['id', 'child', 'children'];
    }
}
