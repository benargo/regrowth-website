<?php

namespace Tests\Feature\Actions\GameVersion;

use App\Actions\GameVersion\UpdateGameVersion;
use App\Contracts\Actions\GameVersion\CachesSelectableGameVersions;
use App\Enums\Faction;
use App\Models\Boss;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\Phase;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use App\Models\Raid;
use App\Models\WarcraftLogs\Guild;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[Group('platform')]
#[Group('raiding')]
class UpdateGameVersionTest extends TestCase
{
    use RefreshDatabase;

    // ==================== details ====================

    #[Group('happy-path')]
    #[Test]
    public function it_updates_only_the_fields_it_is_given(): void
    {
        $gameVersion = GameVersion::factory()->create([
            'title' => 'Era',
            'realm' => 'Gehennas',
            'faction' => Faction::HORDE,
            'color' => 0x8B7ED0,
            'warcraft_logs_guild_id' => Guild::factory()->create(['id' => 123456])->id,
        ]);

        $this->updateGameVersion($gameVersion, ['realm' => 'Firemaw']);

        $fresh = $gameVersion->fresh();
        $this->assertSame('Firemaw', $fresh->realm);
        $this->assertSame('Era', $fresh->title);
        $this->assertSame(Faction::HORDE, $fresh->faction);
        $this->assertSame('8b7ed0', $fresh->color);
        $this->assertSame(123456, $fresh->warcraft_logs_guild_id);
    }

    #[Test]
    public function it_updates_the_game_version_it_is_given_in_place(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Old title']);

        $this->updateGameVersion($gameVersion, ['title' => 'New title']);

        $this->assertSame('New title', $gameVersion->title);
    }

    #[Test]
    public function it_stores_null_for_a_field_given_as_null(): void
    {
        $gameVersion = GameVersion::factory()->create([
            'realm' => 'Gehennas',
            'faction' => Faction::HORDE,
            'warcraft_logs_guild_id' => Guild::factory()->create(['id' => 123456])->id,
        ]);

        $this->updateGameVersion($gameVersion, [
            'faction' => null,
            'warcraft_logs_guild_id' => null,
        ]);

        $fresh = $gameVersion->fresh();
        $this->assertNull($fresh->faction);
        $this->assertNull($fresh->warcraft_logs_guild_id);
        $this->assertSame('Gehennas', $fresh->realm);
    }

    #[Test]
    public function it_stores_a_color_given_as_a_hex_code(): void
    {
        $gameVersion = GameVersion::factory()->create(['color' => null]);

        $this->updateGameVersion($gameVersion, ['color' => '#4AE832']);

        $this->assertSame('4ae832', $gameVersion->fresh()->color);
    }

    #[Test]
    public function it_clears_the_color_when_given_as_null(): void
    {
        $gameVersion = GameVersion::factory()->create(['color' => '#8B7ED0']);

        $this->updateGameVersion($gameVersion, ['color' => null]);

        $this->assertNull($gameVersion->fresh()->color);
    }

    #[Test]
    public function it_leaves_relationships_alone_when_their_keys_are_not_given(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $race = PlayableRace::factory()->create();
        $gameVersion->playableRaces()->attach($race);
        $phase = Phase::factory()->for($gameVersion)->create();

        $this->updateGameVersion($gameVersion, ['realm' => 'Firemaw']);

        $this->assertSame([$race->id], $gameVersion->playableRaces()->pluck('playable_races.id')->all());
        $this->assertSame($gameVersion->id, $phase->fresh()->game_version_id);
    }

    // ==================== races and classes ====================

    #[Group('happy-path')]
    #[Test]
    public function it_replaces_the_linked_races_and_classes(): void
    {
        $gameVersion = GameVersion::factory()->create();
        [$previousRace, $newRace] = PlayableRace::factory()->count(2)->create();
        [$previousClass, $newClass] = PlayableClass::factory()->count(2)->create();
        $gameVersion->playableRaces()->attach($previousRace);
        $gameVersion->playableClasses()->attach($previousClass);

        $this->updateGameVersion($gameVersion, [
            'playable_race_ids' => [$newRace->id],
            'playable_class_ids' => [$newClass->id],
        ]);

        $this->assertSame([$newRace->id], $gameVersion->playableRaces()->pluck('playable_races.id')->all());
        $this->assertSame([$newClass->id], $gameVersion->playableClasses()->pluck('playable_classes.id')->all());
    }

    #[Test]
    public function it_unlinks_every_race_and_class_given_an_empty_list(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $gameVersion->playableRaces()->attach(PlayableRace::factory()->create());
        $gameVersion->playableClasses()->attach(PlayableClass::factory()->create());

        $this->updateGameVersion($gameVersion, [
            'playable_race_ids' => [],
            'playable_class_ids' => [],
        ]);

        $this->assertSame(0, $gameVersion->playableRaces()->count());
        $this->assertSame(0, $gameVersion->playableClasses()->count());
    }

    // ==================== phases ====================

    #[Group('happy-path')]
    #[Test]
    public function it_links_the_given_phases_and_unlinks_the_rest(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $kept = Phase::factory()->for($gameVersion)->create();
        $dropped = Phase::factory()->for($gameVersion)->create();
        $added = Phase::factory()->create();

        $this->updateGameVersion($gameVersion, ['phase_ids' => [$kept->id, $added->id]]);

        $this->assertSame($gameVersion->id, $kept->fresh()->game_version_id);
        $this->assertSame($gameVersion->id, $added->fresh()->game_version_id);
        $this->assertNull($dropped->fresh()->game_version_id);
    }

    #[Test]
    public function it_moves_a_phase_from_another_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $otherGameVersion = GameVersion::factory()->create();
        $moved = Phase::factory()->for($otherGameVersion)->create();
        $untouched = Phase::factory()->for($otherGameVersion)->create();

        $this->updateGameVersion($gameVersion, ['phase_ids' => [$moved->id]]);

        $this->assertSame($gameVersion->id, $moved->fresh()->game_version_id);
        $this->assertSame($otherGameVersion->id, $untouched->fresh()->game_version_id);
    }

    #[Test]
    public function it_unlinks_every_phase_given_an_empty_list(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phase = Phase::factory()->for($gameVersion)->create();

        $this->updateGameVersion($gameVersion, ['phase_ids' => []]);

        $this->assertNull($phase->fresh()->game_version_id);
    }

    // ==================== unscheduled phase order ====================

    #[Test]
    public function it_reorders_the_unscheduled_phases(): void
    {
        $gameVersion = GameVersion::factory()->create();
        [$first, $second] = Phase::factory()->for($gameVersion)->unscheduled()->count(2)->create();

        $this->updateGameVersion($gameVersion, ['unscheduled_phase_ids' => [$second->id, $first->id]]);

        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);
    }

    // ==================== raids and bosses follow their phase ====================

    #[Group('happy-path')]
    #[Test]
    public function it_brings_a_phases_raids_and_bosses_along_when_the_phase_is_linked(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phase = Phase::factory()->create();
        $raid = Raid::factory()->for($phase)->create();
        $boss = Boss::factory()->for($raid)->create();

        $this->updateGameVersion($gameVersion, ['phase_ids' => [$phase->id]]);

        $this->assertTrue($gameVersion->raids()->whereKey($raid->id)->exists());
        $this->assertTrue($boss->fresh()->raid->phase->gameVersion->is($gameVersion));
    }

    #[Test]
    public function it_drops_a_phases_raids_when_the_phase_is_left_out(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phase = Phase::factory()->for($gameVersion)->create();
        Raid::factory()->for($phase)->create();

        $this->updateGameVersion($gameVersion, ['phase_ids' => []]);

        $this->assertNull($phase->fresh()->game_version_id);
        $this->assertFalse($gameVersion->raids()->exists());
    }

    // ==================== guild ranks ====================

    #[Test]
    public function it_syncs_the_guild_ranks_it_is_given(): void
    {
        Queue::fake();
        $gameVersion = GameVersion::factory()->create();

        $this->updateGameVersion($gameVersion, [
            'guild_ranks' => [['id' => null, 'name' => 'Guild Master', 'count_attendance' => true]],
        ]);

        $this->assertSame(['Guild Master'], $gameVersion->guildRanks()->pluck('name')->all());
    }

    #[Test]
    public function it_leaves_guild_ranks_alone_when_the_key_is_not_given(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $rank = GuildRank::factory()->for($gameVersion)->create();

        $this->updateGameVersion($gameVersion, ['realm' => 'Firemaw']);

        $this->assertModelExists($rank);
    }

    // ==================== media ====================

    #[Group('happy-path')]
    #[Group('laravel-media-library')]
    #[Test]
    #[TestWith([GameVersion::MEDIA_BANNER])]
    #[TestWith([GameVersion::MEDIA_ICON])]
    #[TestWith([GameVersion::MEDIA_LOGO])]
    public function it_attaches_an_uploaded_image_to_its_collection(string $collection): void
    {
        Storage::fake('s3');
        $gameVersion = GameVersion::factory()->create();

        $this->updateGameVersion($gameVersion, [$collection => UploadedFile::fake()->image("{$collection}.png")]);

        $this->assertSame("{$collection}.png", $gameVersion->fresh()->getFirstMedia($collection)?->file_name);
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function it_clears_only_the_collection_given_as_null(): void
    {
        Storage::fake('s3');
        $gameVersion = GameVersion::factory()->create();
        $this->attachImage($gameVersion, GameVersion::MEDIA_BANNER);
        $this->attachImage($gameVersion, GameVersion::MEDIA_LOGO);

        $this->updateGameVersion($gameVersion, [GameVersion::MEDIA_BANNER => null]);

        $fresh = $gameVersion->fresh();
        $this->assertFalse($fresh->hasMedia(GameVersion::MEDIA_BANNER));
        $this->assertTrue($fresh->hasMedia(GameVersion::MEDIA_LOGO));
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function it_leaves_images_alone_when_their_keys_are_not_given(): void
    {
        Storage::fake('s3');
        $gameVersion = GameVersion::factory()->create();
        $this->attachImage($gameVersion, GameVersion::MEDIA_ICON);

        $this->updateGameVersion($gameVersion, ['realm' => 'Firemaw']);

        $this->assertTrue($gameVersion->fresh()->hasMedia(GameVersion::MEDIA_ICON));
    }

    #[Test]
    public function it_updates_columns_alongside_an_image(): void
    {
        Storage::fake('s3');
        $gameVersion = GameVersion::factory()->create(['realm' => 'Gehennas']);

        $this->updateGameVersion($gameVersion, [
            'realm' => 'Firemaw',
            GameVersion::MEDIA_LOGO => UploadedFile::fake()->image('logo.png'),
        ]);

        $fresh = $gameVersion->fresh();
        $this->assertSame('Firemaw', $fresh->realm);
        $this->assertTrue($fresh->hasMedia(GameVersion::MEDIA_LOGO));
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function it_flushes_the_game_versions_cache_now_and_when_the_application_terminates_after_an_upload(): void
    {
        Storage::fake('s3');
        $gameVersion = GameVersion::factory()->create();
        $this->primeGameVersionsCache();

        $this->updateGameVersion($gameVersion, [GameVersion::MEDIA_LOGO => UploadedFile::fake()->image('logo.png')]);

        $this->assertFalse($this->gameVersionsCacheIsPrimed());

        $this->primeGameVersionsCache();
        $this->app->terminate();

        $this->assertFalse($this->gameVersionsCacheIsPrimed());
    }

    // ==================== helpers ====================

    private function primeGameVersionsCache(): void
    {
        Cache::tags([CachesSelectableGameVersions::CACHE_TAG])->put('probe', true, 60);
    }

    private function gameVersionsCacheIsPrimed(): bool
    {
        return Cache::tags([CachesSelectableGameVersions::CACHE_TAG])->has('probe');
    }

    private function attachImage(GameVersion $gameVersion, string $collection): void
    {
        $gameVersion->addMedia(UploadedFile::fake()->image("{$collection}.png"))->toMediaCollection($collection);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateGameVersion(GameVersion $gameVersion, array $data): void
    {
        UpdateGameVersion::run($gameVersion, $data);
    }
}
