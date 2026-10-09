<?php

namespace Tests\Feature\Observers\WarcraftLogs;

use App\Models\Report;
use App\Models\WarcraftLogs\GuildTag;
use App\Observers\WarcraftLogs\GuildTagObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
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

    #[Test]
    public function changing_count_attendance_flushes_the_attendance_cache(): void
    {
        $guildTag = GuildTag::factory()->doesNotCountAttendance()->create();
        Cache::tags(['attendance'])->put('stats', 'cached', 60);

        $guildTag->update(['count_attendance' => true]);

        $this->assertNull(Cache::tags(['attendance'])->get('stats'));
    }

    #[Test]
    public function changing_only_the_name_keeps_the_attendance_cache(): void
    {
        $guildTag = GuildTag::factory()->create(['name' => 'Old']);
        Cache::tags(['attendance'])->put('stats', 'cached', 60);

        $guildTag->update(['name' => 'New']);

        $this->assertSame('cached', Cache::tags(['attendance'])->get('stats'));
    }

    #[Test]
    public function deleting_a_counting_tag_flushes_the_attendance_cache(): void
    {
        $guildTag = GuildTag::factory()->countsAttendance()->create();
        Cache::tags(['attendance'])->put('stats', 'cached', 60);

        $guildTag->delete();

        $this->assertNull(Cache::tags(['attendance'])->get('stats'));
    }

    #[Test]
    public function deleting_a_non_counting_tag_keeps_the_attendance_cache(): void
    {
        $guildTag = GuildTag::factory()->doesNotCountAttendance()->create();
        Cache::tags(['attendance'])->put('stats', 'cached', 60);

        $guildTag->delete();

        $this->assertSame('cached', Cache::tags(['attendance'])->get('stats'));
    }

    #[Test]
    public function updating_a_tag_does_not_resave_its_reports(): void
    {
        $guildTag = GuildTag::factory()->create();
        $report = Report::factory()->withGuildTag($guildTag)->create();
        $updatedAt = $report->fresh()->updated_at;
        $this->travel(1)->minutes();

        $guildTag->update(['count_attendance' => ! $guildTag->count_attendance]);

        $this->assertTrue($report->fresh()->updated_at->equalTo($updatedAt));
    }
}
