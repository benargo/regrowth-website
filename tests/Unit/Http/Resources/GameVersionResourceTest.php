<?php

namespace Tests\Unit\Http\Resources;

use App\Enums\Faction;
use App\Enums\Theme;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Http\Resources\GameVersionResource;
use App\Models\GameVersion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
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
        $this->assertArrayHasKey('slug', $array);
        $this->assertArrayHasKey('theme', $array);
        $this->assertArrayHasKey('banner_class', $array);
        $this->assertArrayHasKey('banner_url', $array);
        $this->assertArrayHasKey('icon_url', $array);
        $this->assertArrayHasKey('logo_url', $array);
    }

    #[Test]
    public function it_returns_the_slug_in_the_default_shape(): void
    {
        $gameVersion = GameVersion::factory()->create(['slug' => 'tbc']);

        $array = (new GameVersionResource($gameVersion))->resolve(new Request);

        $this->assertSame('tbc', $array['slug']);
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

        $this->assertSame(['id', 'title', 'slug', 'theme', 'banner_class', 'banner_url', 'icon_url', 'logo_url'], array_keys($array));
    }

    // ==================== images ====================

    #[Group('laravel-media-library')]
    #[Test]
    #[TestWith([GameVersion::MEDIA_BANNER, 'banner_url'])]
    #[TestWith([GameVersion::MEDIA_ICON, 'icon_url'])]
    #[TestWith([GameVersion::MEDIA_LOGO, 'logo_url'])]
    public function it_returns_the_url_of_an_uploaded_image(string $collection, string $key): void
    {
        Storage::fake('public');
        $gameVersion = GameVersion::factory()->create();
        $media = $gameVersion->addMedia(UploadedFile::fake()->image("{$collection}.png"))->toMediaCollection($collection);

        $array = (new GameVersionResource($gameVersion->fresh()))->resolve(new Request);

        $this->assertSame($media->getUrl(), $array[$key]);
    }

    #[Test]
    public function it_returns_null_urls_when_no_images_are_uploaded(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $array = (new GameVersionResource($gameVersion))->resolve(new Request);

        $this->assertNull($array['banner_url']);
        $this->assertNull($array['icon_url']);
        $this->assertNull($array['logo_url']);
    }

    // ==================== management scope ====================

    #[Test]
    public function the_management_scope_exposes_every_editable_field(): void
    {
        $gameVersion = GameVersion::factory()->make([
            'id' => 7,
            'title' => 'Burning Crusade Classic (Anniversary)',
            'slug' => 'tbc',
            'realm' => 'Thunderstrike',
            'guild_name' => 'Regrowth',
            'faction' => Faction::ALLIANCE,
            'release_date' => Carbon::create(2026, 2, 6, 0, 0, 0, 'Europe/Paris'),
            'theme' => Theme::CLASSIC,
            'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY,
            'warcraftlogs_guild' => 774848,
            'warcraftlogs_namespace' => WarcraftLogsNamespace::ANNIVERSARY,
        ]);

        $array = GameVersionResource::forManagement($gameVersion)->resolve(new Request);

        $this->assertSame([
            'id' => 7,
            'title' => 'Burning Crusade Classic (Anniversary)',
            'slug' => 'tbc',
            'theme' => Theme::CLASSIC,
            'banner_class' => 'bg-raid-black-temple',
            'banner_url' => null,
            'icon_url' => null,
            'logo_url' => null,
            'realm' => 'Thunderstrike',
            'guild_name' => 'Regrowth',
            'faction' => Faction::ALLIANCE,
            'release_date' => '2026-02-06',
            'blizzard' => ['namespace' => BlizzardNamespace::ANNIVERSARY],
            'warcraftlogs' => [
                'guild' => 774848,
                'namespace' => [
                    'value' => WarcraftLogsNamespace::ANNIVERSARY,
                    'label' => 'The Burning Crusade Classic Anniversary',
                ],
            ],
        ], $array);
    }

    #[Test]
    public function the_management_scope_returns_null_for_blank_optional_fields(): void
    {
        $gameVersion = GameVersion::factory()->make([
            'realm' => null,
            'faction' => null,
            'blizzard_namespace' => null,
            'warcraftlogs_guild' => null,
            'warcraftlogs_namespace' => null,
        ]);

        $array = GameVersionResource::forManagement($gameVersion)->resolve(new Request);

        $this->assertNull($array['realm']);
        $this->assertNull($array['faction']);
        $this->assertNull($array['blizzard']['namespace']);
        $this->assertNull($array['warcraftlogs']['guild']);
        $this->assertSame(['value' => null, 'label' => null], $array['warcraftlogs']['namespace']);
    }

    #[Test]
    public function the_management_scope_includes_usage_counts_only_when_they_are_loaded(): void
    {
        $gameVersion = GameVersion::factory()->make();
        $gameVersion->setAttribute('phases_count', 2);
        $gameVersion->setAttribute('guild_tags_count', 3);

        $array = GameVersionResource::forManagement($gameVersion)->resolve(new Request);

        $this->assertSame(2, $array['phases_count']);
        $this->assertArrayNotHasKey('guild_tags_count', $array);
        $this->assertArrayNotHasKey('characters_count', $array);
    }
}
