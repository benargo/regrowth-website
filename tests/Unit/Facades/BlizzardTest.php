<?php

namespace Tests\Unit\Facades;

use App\Facades\Blizzard;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('blizzard-integration')]
class BlizzardTest extends TestCase
{
    #[Test]
    public function it_resolves_to_the_blizzard_connector(): void
    {
        $this->assertInstanceOf(BlizzardConnector::class, Blizzard::getFacadeRoot());
    }
}
