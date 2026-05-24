<?php

namespace Tests\Unit\Services\Discord\Resources;

use App\Services\Discord\Resources\RoleColors;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\TestCase;

class RoleColorsTest extends TestCase
{
    #[Test]
    public function it_constructs_with_required_primary_color(): void
    {
        $colors = RoleColors::from(['primary_color' => 16711680]);

        $this->assertSame(16711680, $colors->primary_color);
        $this->assertInstanceOf(Optional::class, $colors->secondary_color);
        $this->assertInstanceOf(Optional::class, $colors->tertiary_color);
    }

    #[Test]
    public function it_stores_secondary_and_tertiary_colors(): void
    {
        $colors = RoleColors::from([
            'primary_color' => 16711680,
            'secondary_color' => 65280,
            'tertiary_color' => 255,
        ]);

        $this->assertSame(16711680, $colors->primary_color);
        $this->assertSame(65280, $colors->secondary_color);
        $this->assertSame(255, $colors->tertiary_color);
    }

    #[Test]
    public function it_accepts_null_for_secondary_and_tertiary_color(): void
    {
        $colors = RoleColors::from([
            'primary_color' => 16711680,
            'secondary_color' => null,
            'tertiary_color' => null,
        ]);

        $this->assertNull($colors->secondary_color);
        $this->assertNull($colors->tertiary_color);
    }
}
