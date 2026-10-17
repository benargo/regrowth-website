<?php

namespace App\Data\Transformers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

/**
 * Transforms keyed groups of nullable Eloquent models into the arrays their API
 * resources emit, keeping each group's key and transforming each group as
 * `ToResourceListTransformer` does.
 */
class ToGroupedResourceListTransformer implements Transformer
{
    /**
     * @param  class-string<JsonResource>|null  $resource
     */
    public function __construct(
        private readonly ?string $resource = null,
        private readonly ?string $scope = null,
    ) {}

    /**
     * @param  iterable<array-key, iterable<int, Model|null>>  $value
     * @return array<array-key, array<int, array<string, mixed>>>
     */
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): array
    {
        $listTransformer = new ToResourceListTransformer($this->resource, $this->scope);

        return collect($value)
            ->map(fn (iterable $models): array => $listTransformer->transform($property, $models, $context))
            ->all();
    }
}
