<?php

namespace Tests\Support\Resources;

use App\Attributes\Http\Resources\ScopeDefinition;
use App\Contracts\Http\Resources\ScopedResource;
use App\Http\Resources\Concerns\HasScopes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Array-backed resource with `summary` and `detail` scopes.
 *
 * @property array{id: int, name: string, secret: string} $resource
 */
class ScopedTestResource extends JsonResource implements ScopedResource
{
    use HasScopes;

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->scoped([
            'id' => $this->resource['id'],
            'name' => $this->resource['name'],
            'secret' => $this->resource['secret'],
            'extra' => $this->when(false, 'x'),
            $this->mergeWhen(true, ['merged' => 'm']),
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function scopeDefinitions(): array
    {
        return ['summary' => $this->summary(), 'detail' => $this->detailed()];
    }

    /**
     * @return list<string>
     */
    #[ScopeDefinition]
    protected function summary(): array
    {
        return ['id', 'name'];
    }

    /**
     * @return list<string>
     */
    #[ScopeDefinition('detail')]
    protected function detailed(): array
    {
        return ['id', 'name', 'secret', 'extra', 'merged'];
    }
}
