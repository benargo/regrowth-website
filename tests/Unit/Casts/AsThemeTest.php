<?php

namespace Tests\Unit\Casts;

use App\Casts\AsTheme;
use App\Enums\Theme;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ValueError;

#[Group('platform')]
class AsThemeTest extends TestCase
{
    #[Test]
    public function get_returns_theme_enum_from_string_value(): void
    {
        $cast = new AsTheme;
        $model = $this->createStub(Model::class);

        $result = $cast->get($model, 'theme', 'forever', []);

        $this->assertSame(Theme::FOREVER, $result);
    }

    #[Group('error-handling')]
    #[Test]
    public function get_throws_value_error_when_value_is_not_a_valid_theme(): void
    {
        $cast = new AsTheme;
        $model = $this->createStub(Model::class);

        $this->expectException(ValueError::class);

        $cast->get($model, 'theme', 'not-a-theme', []);
    }

    #[Test]
    public function get_defaults_to_the_configured_theme_when_value_is_null(): void
    {
        config(['app.theme' => 'forever']);
        $cast = new AsTheme;
        $model = $this->createStub(Model::class);

        $result = $cast->get($model, 'theme', null, []);

        $this->assertSame(Theme::FOREVER, $result);
    }

    #[Test]
    public function set_returns_the_value_of_a_theme_instance(): void
    {
        $cast = new AsTheme;
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'theme', Theme::FOREVER, []);

        $this->assertSame('forever', $result);
    }

    #[Test]
    public function set_passes_through_a_raw_string_value(): void
    {
        $cast = new AsTheme;
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'theme', 'forever', []);

        $this->assertSame('forever', $result);
    }

    #[Test]
    public function set_defaults_to_the_configured_theme_when_value_is_null(): void
    {
        config(['app.theme' => 'forever']);
        $cast = new AsTheme;
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'theme', null, []);

        $this->assertSame('forever', $result);
    }
}
