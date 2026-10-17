<?php

namespace App\Data\Transformers;

use App\Contracts\Http\Resources\ScopedResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

/**
 * Transforms a list of nullable Eloquent models into the arrays their API resources
 * emit, dropping the nulls and keeping each model's original index: the given
 * resource class, or each model's default resource when none is given, limited
 * to the given scope when one is given and the resource is scoped.
 */
class ToResourceListTransformer implements Transformer
{
    /**
     * @param  class-string<JsonResource>|null  $resource
     */
    public function __construct(
        private readonly ?string $resource = null,
        private readonly ?string $scope = null,
    ) {}

    /**
     * @param  array<int, Model|null>  $value
     * @return array<int, array<string, mixed>>
     */
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): array
    {
        return collect($value)
            ->filter()
            ->map(fn (Model $model): array => $this->resolve($model))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolve(Model $model): array
    {
        $resource = $model->toResource($this->resource);

        if ($this->scope !== null && $resource instanceof ScopedResource) {
            $resource->scope($this->scope);
        }

        return $resource->resolve();
    }
}
