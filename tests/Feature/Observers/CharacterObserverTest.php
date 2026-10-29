<?php

namespace Tests\Feature\Observers;

use App\Jobs\Addon\BuildExportFile;
use App\Models\Character;
use App\Models\GameVersion;
use App\Observers\CharacterObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ReleasesAddonExportLocks;
use Tests\TestCase;

#[Group('characters')]
class CharacterObserverTest extends TestCase
{
    use RefreshDatabase;
    use ReleasesAddonExportLocks;

    #[Test]
    public function created_flushes_characters_cache_tag(): void
    {
        Cache::tags(['characters'])->put('test-key', 'test-value', 60);

        $observer = new CharacterObserver;
        $observer->created(Character::factory()->make());

        $this->assertNull(Cache::tags(['characters'])->get('test-key'));
    }

    #[Test]
    public function updated_flushes_characters_cache_tag(): void
    {
        Cache::tags(['characters'])->put('test-key', 'test-value', 60);

        $observer = new CharacterObserver;
        $observer->updated(Character::factory()->make());

        $this->assertNull(Cache::tags(['characters'])->get('test-key'));
    }

    #[Test]
    public function deleted_flushes_characters_cache_tag(): void
    {
        Cache::tags(['characters'])->put('test-key', 'test-value', 60);

        $observer = new CharacterObserver;
        $observer->deleted(Character::factory()->make());

        $this->assertNull(Cache::tags(['characters'])->get('test-key'));
    }

    #[Test]
    public function created_only_flushes_characters_tag_not_other_tags(): void
    {
        Cache::tags(['other-tag'])->put('other-key', 'other-value', 60);

        $observer = new CharacterObserver;
        $observer->created(Character::factory()->make());

        $this->assertSame('other-value', Cache::tags(['other-tag'])->get('other-key'));
    }

    #[Test]
    public function updated_schedules_addon_export_build_for_the_characters_game_version(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $character = Character::factory()->forGameVersion($gameVersion)->create();
        $this->releaseAddonExportLocks();
        Bus::fake([BuildExportFile::class]);

        $character->update(['name' => 'Renamed']);

        Bus::assertDispatchedTimes(BuildExportFile::class, 1);
        Bus::assertDispatched(BuildExportFile::class, fn (BuildExportFile $job) => $job->gameVersionId === $gameVersion->id && $job->delay !== null);
    }

    #[Test]
    public function deleted_schedules_addon_export_build_for_the_characters_game_version(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $character = Character::factory()->forGameVersion($gameVersion)->create();
        $this->releaseAddonExportLocks();
        Bus::fake([BuildExportFile::class]);

        $character->delete();

        Bus::assertDispatched(BuildExportFile::class, fn (BuildExportFile $job) => $job->gameVersionId === $gameVersion->id);
    }

    #[Test]
    public function moving_a_character_to_another_game_version_rebuilds_both(): void
    {
        $from = GameVersion::factory()->fetchableRoster()->create();
        $to = GameVersion::factory()->fetchableRoster()->create();
        $character = Character::factory()->forGameVersion($from)->create();
        $this->releaseAddonExportLocks();
        Bus::fake([BuildExportFile::class]);

        $character->update(['game_version_id' => $to->id]);

        Bus::assertDispatchedTimes(BuildExportFile::class, 2);
        Bus::assertDispatched(BuildExportFile::class, fn (BuildExportFile $job) => $job->gameVersionId === $from->id);
        Bus::assertDispatched(BuildExportFile::class, fn (BuildExportFile $job) => $job->gameVersionId === $to->id);
    }

    #[Test]
    public function created_does_not_schedule_addon_export_build(): void
    {
        Bus::fake();

        $observer = new CharacterObserver;
        $observer->created(Character::factory()->make());

        Bus::assertNotDispatched(BuildExportFile::class);
    }

    #[Test]
    public function observer_is_registered_on_character_model(): void
    {
        $attributes = (new \ReflectionClass(Character::class))
            ->getAttributes(ObservedBy::class);

        $this->assertNotEmpty($attributes);
        $this->assertContains(CharacterObserver::class, $attributes[0]->getArguments()[0]);
    }
}
