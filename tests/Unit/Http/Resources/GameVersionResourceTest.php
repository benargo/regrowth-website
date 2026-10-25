<?php

namespace Tests\Unit\Http\Resources;

use App\Enums\Faction;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Http\Resources\GameVersionResource;
use App\Models\GameVersion;
use App\Models\WarcraftLogs\Guild;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
        $gameVersion = GameVersion::factory()->create()->load('media');

        $array = (new GameVersionResource($gameVersion))->resolve(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('slug', $array);
        $this->assertArrayHasKey('color', $array);
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
    public function it_returns_the_color_as_bare_hex(): void
    {
        $gameVersion = GameVersion::factory()->create(['color' => '#8B7ED0']);

        $array = (new GameVersionResource($gameVersion->fresh()))->resolve(new Request);

        $this->assertSame('8b7ed0', $array['color']);
    }

    #[Test]
    public function it_returns_a_null_color_when_none_is_set(): void
    {
        $gameVersion = GameVersion::factory()->create(['color' => null]);

        $array = (new GameVersionResource($gameVersion->fresh()))->resolve(new Request);

        $this->assertNull($array['color']);
    }

    #[Test]
    public function it_does_not_expose_extra_keys(): void
    {
        $gameVersion = GameVersion::factory()->create()->load('media');

        $array = (new GameVersionResource($gameVersion))->resolve(new Request);

        $this->assertSame(
            ['id', 'title', 'slug', 'color', 'banner_url', 'icon_url', 'logo_url', 'realm', 'guild_name', 'uses_surnames', 'faction', 'release_date', 'blizzard'],
            array_keys($array),
        );
    }

    // ==================== images ====================

    #[Group('laravel-media-library')]
    #[Test]
    #[TestWith([GameVersion::MEDIA_BANNER, 'banner_url'])]
    #[TestWith([GameVersion::MEDIA_ICON, 'icon_url'])]
    #[TestWith([GameVersion::MEDIA_LOGO, 'logo_url'])]
    public function it_returns_the_url_of_an_uploaded_image(string $collection, string $key): void
    {
        Storage::fake('s3');
        $gameVersion = GameVersion::factory()->create();
        $media = $gameVersion->addMedia(UploadedFile::fake()->image("{$collection}.png"))->toMediaCollection($collection);

        $array = (new GameVersionResource($gameVersion->fresh('media')))->resolve(new Request);

        $this->assertSame($media->getUrl(), $array[$key]);
    }

    #[Test]
    public function it_returns_null_urls_when_no_images_are_uploaded(): void
    {
        $gameVersion = GameVersion::factory()->create()->load('media');

        $array = (new GameVersionResource($gameVersion))->resolve(new Request);

        $this->assertNull($array['banner_url']);
        $this->assertNull($array['icon_url']);
        $this->assertNull($array['logo_url']);
    }

    #[Test]
    public function it_omits_the_image_urls_when_media_is_not_loaded(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $array = (new GameVersionResource($gameVersion))->resolve(new Request);

        $this->assertArrayNotHasKey('banner_url', $array);
        $this->assertArrayNotHasKey('icon_url', $array);
        $this->assertArrayNotHasKey('logo_url', $array);
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
            'color' => 0x4AE832,
            'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY,
            'warcraft_logs_guild_id' => 774848,
        ]);
        $gameVersion->setRelation('warcraftLogsGuild', Guild::factory()->make([
            'id' => 774848,
            'namespace' => WarcraftLogsNamespace::Anniversary,
        ]));

        $array = $gameVersion->loadMissing(['media', 'warcraftLogsGuild'])->toResource()->scope('management')->resolve(new Request);

        $this->assertSame([
            'id' => 7,
            'title' => 'Burning Crusade Classic (Anniversary)',
            'slug' => 'tbc',
            'color' => '4ae832',
            'banner_url' => null,
            'icon_url' => null,
            'logo_url' => null,
            'realm' => 'Thunderstrike',
            'guild_name' => 'Regrowth',
            'uses_surnames' => false,
            'faction' => Faction::ALLIANCE,
            'release_date' => '2026-02-06',
            'blizzard' => ['namespace' => BlizzardNamespace::ANNIVERSARY],
            'warcraftlogs' => [
                'guild' => 774848,
                'namespace' => [
                    'value' => WarcraftLogsNamespace::Anniversary,
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
            'warcraft_logs_guild_id' => null,
        ]);

        $array = $gameVersion->loadMissing('warcraftLogsGuild')->toResource()->scope('management')->resolve(new Request);

        $this->assertNull($array['realm']);
        $this->assertNull($array['faction']);
        $this->assertNull($array['blizzard']['namespace']);
        $this->assertNull($array['warcraftlogs']['guild']);
        $this->assertSame(['value' => null, 'label' => null], $array['warcraftlogs']['namespace']);
        $this->assertArrayNotHasKey('url', $array['warcraftlogs']);
    }

    #[Test]
    public function the_management_scope_includes_usage_counts_only_when_they_are_loaded(): void
    {
        $gameVersion = GameVersion::factory()->make();
        $gameVersion->setAttribute('phases_count', 2);
        $gameVersion->setAttribute('guild_tags_count', 3);

        $array = $gameVersion->loadMissing('warcraftLogsGuild')->toResource()->scope('management')->resolve(new Request);

        $this->assertSame(2, $array['phases_count']);
        $this->assertArrayNotHasKey('guild_tags_count', $array);
        $this->assertArrayNotHasKey('characters_count', $array);
    }

    #[Test]
    public function the_management_scope_includes_links_only_when_they_are_attached(): void
    {
        $gameVersion = GameVersion::factory()->make();
        $links = ['edit' => 'https://example.test/edit'];

        $withoutLinks = $gameVersion->loadMissing('warcraftLogsGuild')->toResource()->scope('management')->resolve(new Request);
        $withLinks = $gameVersion->loadMissing('warcraftLogsGuild')->toResource()->scope('management')->withLinks($links)->resolve(new Request);

        $this->assertArrayNotHasKey('links', $withoutLinks);
        $this->assertSame($links, $withLinks['links']);
    }

    #[Test]
    public function the_management_scope_omits_warcraftlogs_when_the_guild_is_not_loaded(): void
    {
        $array = GameVersion::factory()->create()->fresh()->toResource()->scope('management')->resolve(new Request);

        $this->assertArrayNotHasKey('warcraftlogs', $array);
    }

    #[Test]
    public function the_unscoped_resource_does_not_query_the_warcraft_logs_guild(): void
    {
        GameVersion::factory()->count(2)->create(['warcraft_logs_guild_id' => Guild::factory()->create()->id]);
        $gameVersions = GameVersion::all();

        DB::enableQueryLog();
        GameVersionResource::collection($gameVersions)->resolve(new Request);

        $this->assertEmpty(array_filter(
            DB::getQueryLog(),
            fn (array $query): bool => str_contains($query['query'], 'warcraft_logs_guilds'),
        ));
    }

    #[Test]
    public function the_management_scope_keeps_per_item_links_in_a_collection(): void
    {
        $links = ['edit' => 'https://example.test/edit'];
        $collection = GameVersionResource::collection([GameVersion::factory()->make()])->scope('management');
        $collection->collection->first()->withLinks($links);

        $this->assertSame($links, $collection->resolve(new Request)[0]['links']);
    }

    // ==================== switcher scope ====================

    #[Test]
    public function the_switcher_scope_exposes_only_the_switcher_fields(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $array = $gameVersion->load(['media', 'warcraftLogsGuild'])->toResource()->scope('switcher')->resolve(new Request);

        $this->assertSame(['id', 'title', 'slug', 'color', 'icon_url', 'release_date'], array_keys($array));
    }

    // ==================== metadata ====================

    #[Test]
    public function it_returns_the_accepted_types_of_each_media_collection_as_metadata(): void
    {
        $gameVersion = GameVersion::factory()->make();

        $metadata = (new GameVersionResource($gameVersion))->with(new Request);

        $this->assertSame([
            'media' => [
                GameVersion::MEDIA_BANNER => ['accepts' => ['image/jpeg', 'image/png', 'image/webp']],
                GameVersion::MEDIA_ICON => ['accepts' => ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml']],
                GameVersion::MEDIA_LOGO => ['accepts' => ['image/jpeg', 'image/png', 'image/webp']],
            ],
        ], $metadata);
    }
}
