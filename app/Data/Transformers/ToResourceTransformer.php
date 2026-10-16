<?php

namespace App\Data\Transformers;

use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

/**
 * Transforms an Eloquent model into the array its default API resource emits.
 */
class ToResourceTransformer implements Transformer
{
    /**
     * @param  Model  $value
     * @return array<string, mixed>
     */
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): array
    {
        return $value->toResource()->resolve();
    }
}
