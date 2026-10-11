<?php

namespace Tests\Unit\Enums;

use App\Enums\Theme;
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
}
