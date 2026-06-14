<?php

namespace Tests\Unit\Facades;

use App\Facades\BlizzardRenderPath;
use App\Http\Integrations\Blizzard\Support\MirrorPaths;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('blizzard-integration')]
class BlizzardRenderPathTest extends TestCase
{
    #[Test]
    public function it_resolves_to_the_mirror_path_resolver(): void
    {
        $this->assertInstanceOf(MirrorPaths::class, BlizzardRenderPath::getFacadeRoot());
    }
}
