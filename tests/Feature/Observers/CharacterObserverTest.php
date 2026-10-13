<?php

namespace Tests\Feature\Observers;

use App\Jobs\BuildAddonExportFile;
use App\Models\Character;
use App\Observers\CharacterObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class CharacterObserverTest extends TestCase
{
    use RefreshDatabase;

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
    public function updated_schedules_addon_export_build(): void
    {
        Bus::fake();

        $observer = new CharacterObserver;
        $observer->updated(Character::factory()->make());

        Bus::assertDispatched(BuildAddonExportFile::class);
    }

    #[Test]
    public function deleted_schedules_addon_export_build(): void
    {
        Bus::fake();

        $observer = new CharacterObserver;
        $observer->deleted(Character::factory()->make());

        Bus::assertDispatched(BuildAddonExportFile::class);
    }

    #[Test]
    public function created_does_not_schedule_addon_export_build(): void
    {
        Bus::fake();

        $observer = new CharacterObserver;
        $observer->created(Character::factory()->make());

        Bus::assertNotDispatched(BuildAddonExportFile::class);
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
