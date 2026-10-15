<?php

namespace Tests\Unit\Http\Integrations\Discord\Data\Guilds;

use App\Http\Integrations\Discord\Data\Guilds\RoleColorsData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\TestCase;

#[Group('discord-integration')]
class RoleColorsDataTest extends TestCase
{
    #[Test]
    public function it_constructs_with_required_primary_color(): void
    {
        $colors = RoleColorsData::from(['primary_color' => 16711680]);

        $this->assertSame(16711680, $colors->primaryColor);
        $this->assertInstanceOf(Optional::class, $colors->secondaryColor);
        $this->assertInstanceOf(Optional::class, $colors->tertiaryColor);
    }

    #[Test]
    public function it_stores_secondary_and_tertiary_colors(): void
    {
        $colors = RoleColorsData::from([
            'primary_color' => 16711680,
            'secondary_color' => 65280,
            'tertiary_color' => 255,
        ]);

        $this->assertSame(16711680, $colors->primaryColor);
        $this->assertSame(65280, $colors->secondaryColor);
        $this->assertSame(255, $colors->tertiaryColor);
    }

    #[Test]
    public function it_accepts_null_for_secondary_and_tertiary_color(): void
    {
        $colors = RoleColorsData::from([
            'primary_color' => 16711680,
            'secondary_color' => null,
            'tertiary_color' => null,
        ]);

        $this->assertNull($colors->secondaryColor);
        $this->assertNull($colors->tertiaryColor);
    }
}
