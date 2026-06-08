<?php

namespace App\Http\Integrations\Blizzard\Data\Casts;

use Illuminate\Support\Uri;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

class UriTransformer implements Transformer
{
    /**
     * Transform a {@see Uri} value object back to its string form so it
     * serialises as a plain URL in array/JSON output.
     */
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): string
    {
        return (string) $value;
    }
}
