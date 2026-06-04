<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Support;

use App\Http\Integrations\Blizzard\Region;
use App\Http\Integrations\Blizzard\Support\MirrorPaths;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MirrorPathsTest extends TestCase
{
    #[Test]
    public function it_strips_host_and_region_from_render_world_of_warcraft_urls(): void
    {
        $resolver = new MirrorPaths(Region::EU);

        $path = $resolver->fromUrl('https://render.worldofwarcraft.com/eu/icons/56/inv_misc_questionmark.jpg');

        $this->assertSame('blizzard-cdn/icons/56/inv_misc_questionmark.jpg', $path);
    }

    #[Test]
    public function it_handles_regional_subdomain_hosts(): void
    {
        $resolver = new MirrorPaths(Region::EU);

        $path = $resolver->fromUrl('https://render-eu.worldofwarcraft.com/zone-icons/some-zone.png');

        $this->assertSame('blizzard-cdn/zone-icons/some-zone.png', $path);
    }

    #[Test]
    public function it_preserves_query_string_free_paths(): void
    {
        $resolver = new MirrorPaths(Region::US);

        $path = $resolver->fromUrl('https://render.worldofwarcraft.com/us/icons/56/spell_holy_flashheal.jpg?versionhash=abc');

        $this->assertSame('blizzard-cdn/icons/56/spell_holy_flashheal.jpg', $path);
    }

    #[Test]
    public function it_returns_null_for_urls_outside_the_render_host(): void
    {
        $resolver = new MirrorPaths(Region::EU);

        $this->assertNull($resolver->fromUrl('https://example.com/foo.jpg'));
    }
}
