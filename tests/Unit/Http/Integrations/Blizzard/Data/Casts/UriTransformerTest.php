<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\Casts;

use App\Http\Integrations\Blizzard\Data\Casts\UriTransformer;
use Illuminate\Support\Uri;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Tests\TestCase;

#[Group('blizzard-integration')]
class UriTransformerTest extends TestCase
{
    #[Test]
    public function it_transforms_a_uri_to_its_string_form(): void
    {
        $uri = Uri::of('https://render.worldofwarcraft.com/eu/icons/56/shaman.jpg');

        $transformed = (new UriTransformer)->transform(
            Mockery::mock(DataProperty::class),
            $uri,
            Mockery::mock(TransformationContext::class),
        );

        $this->assertSame('https://render.worldofwarcraft.com/eu/icons/56/shaman.jpg', $transformed);
    }
}
