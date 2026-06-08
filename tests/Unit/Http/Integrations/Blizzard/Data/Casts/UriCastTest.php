<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\Casts;

use App\Http\Integrations\Blizzard\Data\Casts\UriCast;
use Illuminate\Support\Uri;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;
use Tests\TestCase;

class UriCastTest extends TestCase
{
    #[Test]
    public function it_casts_a_string_to_a_uri(): void
    {
        $uri = $this->cast('https://render.worldofwarcraft.com/eu/icons/56/shaman.jpg');

        $this->assertInstanceOf(Uri::class, $uri);
        $this->assertSame('render.worldofwarcraft.com', $uri->host());
        $this->assertSame('eu/icons/56/shaman.jpg', $uri->path());
    }

    #[Test]
    public function it_passes_an_existing_uri_through_unchanged(): void
    {
        $existing = Uri::of('https://render.worldofwarcraft.com/eu/icons/56/druid.jpg');

        $this->assertSame($existing, $this->cast($existing));
    }

    #[Test]
    public function the_cast_uri_serialises_back_to_the_original_string(): void
    {
        $url = 'https://render.worldofwarcraft.com/eu/icons/56/warrior.jpg';

        $this->assertSame($url, (string) $this->cast($url));
    }

    private function cast(Uri|string $value): Uri
    {
        return (new UriCast)->cast(
            Mockery::mock(DataProperty::class),
            $value,
            [],
            Mockery::mock(CreationContext::class),
        );
    }
}
