<?php

namespace App\Data\Transformers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

/**
 * Transforms an Eloquent model into the array its API resource emits: the given
 * resource class, or the model's default resource when none is given.
 */
class ToResourceTransformer implements Transformer
{
    /**
     * @param  class-string<JsonResource>|null  $resource
     */
    public function __construct(
        private readonly ?string $resource = null,
    ) {}

    /**
     * @param  Model  $value
     * @return array<string, mixed>
     */
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): array
    {
        return $value->toResource($this->resource)->resolve();
    }
}
