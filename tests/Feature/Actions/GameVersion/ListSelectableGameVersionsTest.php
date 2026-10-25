<?php

namespace Tests\Feature\Actions\GameVersion;

use App\Actions\GameVersion\ListSelectableGameVersions;
use App\Contracts\Actions\GameVersion\CachesSelectableGameVersions;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Models\GameVersion;
use App\Models\WarcraftLogs\Guild;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class ListSelectableGameVersionsTest extends TestCase
{
    use RefreshDatabase;

    // ==================== handle ====================

    #[Group('happy-path')]
    #[Test]
    public function it_lists_every_version_newest_release_first(): void
    {
        $older = GameVersion::factory()->create(['release_date' => now()->subYear()]);
        $unreleased = GameVersion::factory()->create(['release_date' => now()->addMonth()]);
        $latest = GameVersion::factory()->create(['release_date' => now()->subDay()]);

        $this->assertSame(
            [$unreleased->id, $latest->id, $older->id],
            ListSelectableGameVersions::run()->pluck('id')->all()
        );
    }

    #[Test]
    public function it_breaks_release_date_ties_by_id(): void
    {
        $date = now()->subMonth();
        $first = GameVersion::factory()->create(['release_date' => $date]);
        $second = GameVersion::factory()->create(['release_date' => $date]);

        $this->assertSame([$first->id, $second->id], ListSelectableGameVersions::run()->pluck('id')->all());
    }

    #[Test]
    public function it_eager_loads_media(): void
    {
        GameVersion::factory()->create();

        $this->assertTrue(ListSelectableGameVersions::run()->every->relationLoaded('media'));
    }

    #[Test]
    public function it_eager_loads_the_warcraft_logs_guild(): void
    {
        GameVersion::factory()->forGuild()->create();

        $this->assertTrue(ListSelectableGameVersions::run()->every->relationLoaded('warcraftLogsGuild'));
    }

    #[Test]
    public function it_queries_once_per_request(): void
    {
        GameVersion::factory()->create();

        DB::enableQueryLog();
        ListSelectableGameVersions::run();
        ListSelectableGameVersions::run();

        $versionSelects = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_starts_with($query['query'], 'select') && preg_match('/from [`"]game_versions[`"]/', $query['query']));

        $this->assertCount(1, $versionSelects);
    }

    #[Test]
    public function it_serves_the_list_from_the_cache_on_later_requests(): void
    {
        $gameVersions = GameVersion::factory()->count(2)->create();
        ListSelectableGameVersions::run();
        $this->forgetRequestMemo();

        DB::enableQueryLog();
        $listed = ListSelectableGameVersions::run();

        $this->assertSame([], DB::getQueryLog());
        $this->assertEqualsCanonicalizing($gameVersions->modelKeys(), $listed->modelKeys());
    }

    #[Test]
    public function it_hydrates_cached_versions_with_their_media_and_casts(): void
    {
        Storage::fake('public');
        $gameVersion = GameVersion::factory()->create(['color' => 'ff8800']);
        $gameVersion->addMedia(UploadedFile::fake()->image('icon.png'))->toMediaCollection(GameVersion::MEDIA_ICON);
        $iconUrl = $gameVersion->getFirstMediaUrl(GameVersion::MEDIA_ICON);
        ListSelectableGameVersions::run();
        $this->forgetRequestMemo();

        DB::enableQueryLog();
        $listed = ListSelectableGameVersions::run()->sole();

        $this->assertSame($iconUrl, $listed->getFirstMediaUrl(GameVersion::MEDIA_ICON));
        $this->assertSame([], DB::getQueryLog());
        $this->assertTrue($listed->exists);
        $this->assertSame('ff8800', $listed->color);
    }

    #[Test]
    public function it_hydrates_cached_versions_with_their_warcraft_logs_guild(): void
    {
        $guild = Guild::factory()->create(['namespace' => WarcraftLogsNamespace::Forever]);
        $withGuild = GameVersion::factory()->forGuild($guild)->create();
        $withoutGuild = GameVersion::factory()->create(['warcraft_logs_guild_id' => null]);
        ListSelectableGameVersions::run();
        $this->forgetRequestMemo();

        DB::enableQueryLog();
        $listed = ListSelectableGameVersions::run()->keyBy('id');

        $this->assertSame([], DB::getQueryLog());
        $this->assertTrue($listed[$withGuild->id]->warcraftLogsGuild->is($guild));
        $this->assertTrue($listed[$withGuild->id]->warcraftLogsGuild->exists);
        $this->assertSame(WarcraftLogsNamespace::Forever, $listed[$withGuild->id]->warcraftLogsGuild->namespace);
        $this->assertTrue($listed[$withoutGuild->id]->relationLoaded('warcraftLogsGuild'));
        $this->assertNull($listed[$withoutGuild->id]->warcraftLogsGuild);
    }

    #[Test]
    public function it_serves_the_list_from_a_cache_store_that_disallows_object_unserialization(): void
    {
        config([
            'cache.stores.array.serialize' => true,
            'cache.serializable_classes' => false,
        ]);
        Cache::purge('array');

        Storage::fake('public');
        $guild = Guild::factory()->create();
        $gameVersion = GameVersion::factory()->forGuild($guild)->create();
        $gameVersion->addMedia(UploadedFile::fake()->image('icon.png'))->toMediaCollection(GameVersion::MEDIA_ICON);
        $iconUrl = $gameVersion->getFirstMediaUrl(GameVersion::MEDIA_ICON);
        ListSelectableGameVersions::run();
        $this->forgetRequestMemo();

        DB::enableQueryLog();
        $listed = ListSelectableGameVersions::run()->sole();

        $this->assertSame([], DB::getQueryLog());
        $this->assertTrue($listed->is($gameVersion));
        $this->assertTrue($listed->exists);
        $this->assertSame($iconUrl, $listed->getFirstMediaUrl(GameVersion::MEDIA_ICON));
        $this->assertTrue($listed->warcraftLogsGuild->is($guild));
    }

    // ==================== game version events ====================

    #[Test]
    public function it_refreshes_the_cache_when_a_game_version_is_created(): void
    {
        GameVersion::factory()->create();
        ListSelectableGameVersions::run();
        $this->forgetRequestMemo();

        $created = GameVersion::factory()->create();

        $this->assertContains($created->id, ListSelectableGameVersions::run()->modelKeys());
    }

    #[Test]
    public function it_refreshes_the_cache_when_a_game_version_is_updated(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Before']);
        ListSelectableGameVersions::run();
        $this->forgetRequestMemo();

        $gameVersion->update(['title' => 'After']);

        $this->assertSame('After', ListSelectableGameVersions::run()->sole()->title);
    }

    #[Test]
    public function it_refreshes_the_cache_when_a_game_version_is_deleted(): void
    {
        $kept = GameVersion::factory()->create();
        $deleted = GameVersion::factory()->create();
        ListSelectableGameVersions::run();
        $this->forgetRequestMemo();

        $deleted->delete();

        $this->assertSame([$kept->id], ListSelectableGameVersions::run()->modelKeys());
    }

    // ==================== media events ====================

    #[Test]
    public function it_refreshes_the_cache_when_game_version_media_is_added(): void
    {
        Storage::fake('public');
        $gameVersion = GameVersion::factory()->create();
        ListSelectableGameVersions::run();
        $this->forgetRequestMemo();

        $gameVersion->addMedia(UploadedFile::fake()->image('icon.png'))->toMediaCollection(GameVersion::MEDIA_ICON);

        $this->assertNotSame('', ListSelectableGameVersions::run()->sole()->getFirstMediaUrl(GameVersion::MEDIA_ICON));
    }

    #[Test]
    public function it_refreshes_the_cache_when_game_version_media_is_removed(): void
    {
        Storage::fake('public');
        $gameVersion = GameVersion::factory()->create();
        $gameVersion->addMedia(UploadedFile::fake()->image('icon.png'))->toMediaCollection(GameVersion::MEDIA_ICON);
        ListSelectableGameVersions::run();
        $this->forgetRequestMemo();

        $gameVersion->clearMediaCollection(GameVersion::MEDIA_ICON);

        $this->assertSame('', ListSelectableGameVersions::run()->sole()->getFirstMediaUrl(GameVersion::MEDIA_ICON));
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function it_refreshes_the_cache_when_game_version_media_is_replaced(): void
    {
        Storage::fake('public');
        $gameVersion = GameVersion::factory()->create();
        $gameVersion->addMedia(UploadedFile::fake()->image('old.png'))->toMediaCollection(GameVersion::MEDIA_ICON);
        ListSelectableGameVersions::run();
        $this->forgetRequestMemo();

        $gameVersion->addMedia(UploadedFile::fake()->image('new.png'))->toMediaCollection(GameVersion::MEDIA_ICON);

        $this->assertSame(['new.png'], ListSelectableGameVersions::run()->sole()->getMedia(GameVersion::MEDIA_ICON)->pluck('file_name')->all());
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function it_refreshes_the_list_within_the_same_request_when_media_changes(): void
    {
        Storage::fake('public');
        $gameVersion = GameVersion::factory()->create();
        ListSelectableGameVersions::run();

        $gameVersion->addMedia(UploadedFile::fake()->image('icon.png'))->toMediaCollection(GameVersion::MEDIA_ICON);

        $this->assertNotSame('', ListSelectableGameVersions::run()->sole()->getFirstMediaUrl(GameVersion::MEDIA_ICON));
    }

    // ==================== warcraft logs guild events ====================

    #[Test]
    public function it_refreshes_the_cache_when_a_warcraft_logs_guild_is_saved(): void
    {
        $guild = Guild::factory()->create(['namespace' => WarcraftLogsNamespace::Classic]);
        GameVersion::factory()->forGuild($guild)->create();
        ListSelectableGameVersions::run();
        $this->forgetRequestMemo();

        $guild->update(['namespace' => WarcraftLogsNamespace::Forever]);

        $this->assertSame(WarcraftLogsNamespace::Forever, ListSelectableGameVersions::run()->sole()->warcraftLogsGuild->namespace);
    }

    // ==================== helpers ====================

    /**
     * Drop the per-request memo so the next run reads from the cache.
     */
    private function forgetRequestMemo(): void
    {
        request()->attributes->remove(CachesSelectableGameVersions::REQUEST_KEY);
    }
}
