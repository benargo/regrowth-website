<?php

namespace Tests\Unit\Http\Resources;

use App\Enums\Theme;
use App\Http\Resources\GameVersionResource;
use App\Models\GameVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class GameVersionResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $array = (new GameVersionResource($gameVersion))->resolve(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('theme', $array);
        $this->assertArrayHasKey('banner_class', $array);
    }

    #[Test]
    public function it_returns_correct_id(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $array = (new GameVersionResource($gameVersion))->resolve(new Request);

        $this->assertSame($gameVersion->id, $array['id']);
    }

    #[Test]
    public function it_returns_correct_title(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Black Temple']);

        $array = (new GameVersionResource($gameVersion))->resolve(new Request);

        $this->assertSame('Black Temple', $array['title']);
    }

    #[Test]
    public function it_returns_correct_theme(): void
    {
        $gameVersion = GameVersion::factory()->create(['theme' => Theme::FOREVER]);

        $array = (new GameVersionResource($gameVersion))->resolve(new Request);

        $this->assertSame(Theme::FOREVER, $array['theme']);
    }

    #[Test]
    public function it_returns_the_banner_css_class_for_the_theme(): void
    {
        $gameVersion = GameVersion::factory()->create(['theme' => Theme::CLASSIC]);

        $array = (new GameVersionResource($gameVersion))->resolve(new Request);

        $this->assertSame(Theme::CLASSIC->bannerCssClass(), $array['banner_class']);
    }

    #[Test]
    public function it_does_not_expose_extra_keys(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $array = (new GameVersionResource($gameVersion))->resolve(new Request);

        $this->assertSame(['id', 'title', 'theme', 'banner_class'], array_keys($array));
    }
}
