<?php

namespace Tests\Unit\Enums;

use App\Enums\Theme;
use Illuminate\Support\Facades\Vite;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ValueError;

#[Group('platform')]
class ThemeTest extends TestCase
{
    #[Test]
    public function it_backs_classic_with_the_classic_string(): void
    {
        $this->assertSame('classic', Theme::CLASSIC->value);
    }

    #[Test]
    public function it_backs_forever_with_the_forever_string(): void
    {
        $this->assertSame('forever', Theme::FOREVER->value);
    }

    #[Test]
    public function it_resolves_a_known_value_from_a_string(): void
    {
        $this->assertSame(Theme::FOREVER, Theme::from('forever'));
    }

    #[Test]
    public function it_exposes_exactly_the_themes_defined_in_css(): void
    {
        $this->assertSame(
            ['classic', 'forever'],
            array_map(fn (Theme $theme) => $theme->value, Theme::cases())
        );
    }

    // ==================== default() ====================

    #[Test]
    public function it_reads_the_default_from_config(): void
    {
        config(['app.theme' => 'forever']);

        $this->assertSame(Theme::FOREVER, Theme::default());
    }

    #[Test]
    public function it_falls_back_to_classic_when_no_theme_is_configured(): void
    {
        config(['app.theme' => null]);

        $this->assertSame(Theme::CLASSIC, Theme::default());
    }

    #[Test]
    public function it_throws_when_the_configured_theme_is_invalid(): void
    {
        config(['app.theme' => 'nonsense']);

        $this->expectException(ValueError::class);

        Theme::default();
    }

    // ==================== bannerImagePath() ====================

    #[Test]
    public function it_resolves_the_classic_banner_image_through_vite(): void
    {
        Vite::shouldReceive('asset')
            ->once()
            ->with('resources/images/banner_anniversary.webp')
            ->andReturn('https://cdn.example.com/banner_anniversary.webp');

        $this->assertSame('https://cdn.example.com/banner_anniversary.webp', Theme::CLASSIC->bannerImagePath());
    }

    #[Test]
    public function it_resolves_the_forever_banner_image_through_vite(): void
    {
        Vite::shouldReceive('asset')
            ->once()
            ->with('resources/images/banner_camelot.webp')
            ->andReturn('https://cdn.example.com/banner_camelot.webp');

        $this->assertSame('https://cdn.example.com/banner_camelot.webp', Theme::FOREVER->bannerImagePath());
    }

    // ==================== bannerCssClass() ====================

    #[Test]
    public function it_builds_the_classic_banner_css_class_with_the_default_prefix(): void
    {
        $this->assertSame('bg-raid-black-temple', Theme::CLASSIC->bannerCssClass());
    }

    #[Test]
    public function it_builds_the_forever_banner_css_class_with_the_default_prefix(): void
    {
        $this->assertSame('bg-camelot', Theme::FOREVER->bannerCssClass());
    }

    #[Test]
    public function it_builds_the_banner_css_class_with_a_custom_prefix(): void
    {
        $this->assertSame('from-raid-black-temple', Theme::CLASSIC->bannerCssClass('from'));
    }

    // ==================== iconPath() ====================

    #[Test]
    public function it_resolves_the_classic_icon_through_vite(): void
    {
        Vite::shouldReceive('asset')
            ->once()
            ->with('resources/images/icon_tbcclassic.webp')
            ->andReturn('https://cdn.example.com/icon_tbcclassic.webp');

        $this->assertSame('https://cdn.example.com/icon_tbcclassic.webp', Theme::CLASSIC->iconPath());
    }

    #[Test]
    public function it_resolves_the_forever_icon_through_vite(): void
    {
        Vite::shouldReceive('asset')
            ->once()
            ->with('resources/images/icon_camelot.webp')
            ->andReturn('https://cdn.example.com/icon_camelot.webp');

        $this->assertSame('https://cdn.example.com/icon_camelot.webp', Theme::FOREVER->iconPath());
    }
}
