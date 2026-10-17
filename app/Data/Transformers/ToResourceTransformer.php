<?php

namespace App\Data\Transformers;

use App\Contracts\Http\Resources\ScopedResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

/**
 * Transforms an Eloquent model into the array its API resource emits: the given
 * resource class, or the model's default resource when none is given, limited
 * to the given scope when one is given and the resource is scoped.
 */
class ToResourceTransformer implements Transformer
{
    /**
     * @param  class-string<JsonResource>|null  $resource
     */
    public function __construct(
        private readonly ?string $resource = null,
        private readonly ?string $scope = null,
    ) {}

    /**
     * @param  Model  $value
     * @return array<string, mixed>
     */
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): array
    {
        $resource = $value->toResource($this->resource);

        if ($this->scope !== null && $resource instanceof ScopedResource) {
            $resource->scope($this->scope);
        }

        return $resource->resolve();
    }
}
