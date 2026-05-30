<?php

namespace Tests\Unit\Facades;

use App\Facades\BlizzardAsset;
use App\Http\Integrations\Blizzard\RenderConnector;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlizzardAssetTest extends TestCase
{
    #[Test]
    public function it_resolves_to_the_render_connector(): void
    {
        $this->assertInstanceOf(RenderConnector::class, BlizzardAsset::getFacadeRoot());
    }
}
