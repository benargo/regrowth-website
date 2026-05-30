<?php

namespace Tests\Unit\Facades;

use App\Facades\BlizzardRenderPath;
use App\Http\Integrations\Blizzard\Support\MirrorPathResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlizzardRenderPathTest extends TestCase
{
    #[Test]
    public function it_resolves_to_the_mirror_path_resolver(): void
    {
        $this->assertInstanceOf(MirrorPathResolver::class, BlizzardRenderPath::getFacadeRoot());
    }
}
