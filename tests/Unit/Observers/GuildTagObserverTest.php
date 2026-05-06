<?php

namespace Tests\Unit\Observers;

use App\Models\GuildTag;
use App\Observers\GuildTagObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GuildTagObserverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function created_flushes_db_and_lootcouncil_cache_tags(): void
    {
        Cache::tags(['db', 'lootcouncil'])->put('test-key', 'test-value', 60);

        $observer = new GuildTagObserver;
        $observer->created(GuildTag::factory()->make());

        $this->assertNull(Cache::tags(['db', 'lootcouncil'])->get('test-key'));
    }

    #[Test]
    public function updated_flushes_db_and_lootcouncil_cache_tags(): void
    {
        Cache::tags(['db', 'lootcouncil'])->put('test-key', 'test-value', 60);

        $observer = new GuildTagObserver;
        $observer->updated(GuildTag::factory()->make());

        $this->assertNull(Cache::tags(['db', 'lootcouncil'])->get('test-key'));
    }

    #[Test]
    public function deleted_flushes_db_and_lootcouncil_cache_tags(): void
    {
        Cache::tags(['db', 'lootcouncil'])->put('test-key', 'test-value', 60);

        $observer = new GuildTagObserver;
        $observer->deleted(GuildTag::factory()->make());

        $this->assertNull(Cache::tags(['db', 'lootcouncil'])->get('test-key'));
    }

    #[Test]
    public function created_does_not_flush_unrelated_cache_tags(): void
    {
        Cache::tags(['other-tag'])->put('other-key', 'other-value', 60);

        $observer = new GuildTagObserver;
        $observer->created(GuildTag::factory()->make());

        $this->assertSame('other-value', Cache::tags(['other-tag'])->get('other-key'));
    }

    #[Test]
    public function observer_is_registered_on_guild_tag_model(): void
    {
        $attributes = (new \ReflectionClass(GuildTag::class))
            ->getAttributes(ObservedBy::class);

        $this->assertNotEmpty($attributes);

        $observerClasses = $attributes[0]->getArguments()[0];
        $this->assertContains(GuildTagObserver::class, $observerClasses);
    }
}
