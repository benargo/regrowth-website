<?php

namespace Tests\Feature\Actions\Raid;

use App\Actions\Raid\UpdateRaid;
use App\Models\Boss;
use App\Models\Phase;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class UpdateRaidTest extends TestCase
{
    use RefreshDatabase;

    #[Group('happy-path')]
    #[Test]
    public function it_updates_only_the_fields_it_is_given(): void
    {
        $raid = Raid::factory()->create(['name' => 'Karazhan', 'difficulty' => 'Normal', 'max_players' => 10]);

        UpdateRaid::run($raid, ['name' => 'Karazhan Crypts']);

        $fresh = $raid->fresh();
        $this->assertSame('Karazhan Crypts', $fresh->name);
        $this->assertSame('Normal', $fresh->difficulty);
        $this->assertSame(10, $fresh->max_players);
    }

    #[Test]
    public function it_stores_null_for_a_field_given_as_null(): void
    {
        $raid = Raid::factory()->create(['max_players' => 25, 'color' => '#8B7ED0']);

        UpdateRaid::run($raid, ['max_players' => null, 'color' => null]);

        $fresh = $raid->fresh();
        $this->assertNull($fresh->max_players);
        $this->assertNull($fresh->color);
    }

    #[Test]
    public function it_moves_the_raid_to_another_phase(): void
    {
        $raid = Raid::factory()->create();
        $phase = Phase::factory()->create();

        UpdateRaid::run($raid, ['phase_id' => $phase->id]);

        $this->assertSame($phase->id, $raid->fresh()->phase_id);
    }

    // ==================== boss order ====================

    #[Group('happy-path')]
    #[Test]
    public function it_reorders_the_raids_bosses(): void
    {
        $raid = Raid::factory()->create();
        [$first, $second, $third] = Boss::factory()->for($raid)->count(3)->create();

        UpdateRaid::run($raid, ['boss_ids' => [$third->id, $first->id, $second->id]]);

        $this->assertSame([$third->id, $first->id, $second->id], $raid->bosses()->pluck('id')->all());
    }

    #[Test]
    public function it_updates_columns_and_the_boss_order_together(): void
    {
        $raid = Raid::factory()->create(['name' => 'Karazhan']);
        [$first, $second] = Boss::factory()->for($raid)->count(2)->create();

        UpdateRaid::run($raid, ['name' => 'Return to Karazhan', 'boss_ids' => [$second->id, $first->id]]);

        $this->assertSame('Return to Karazhan', $raid->fresh()->name);
        $this->assertSame([$second->id, $first->id], $raid->bosses()->pluck('id')->all());
    }

    #[Test]
    public function it_flushes_the_raiding_cache_when_only_the_boss_order_changes(): void
    {
        $raid = Raid::factory()->create();
        [$first, $second] = Boss::factory()->for($raid)->count(2)->create();
        Cache::tags(['raiding', 'events'])->put('raids:show', 'stale', now()->addHour());

        UpdateRaid::run($raid, ['boss_ids' => [$second->id, $first->id]]);

        $this->assertFalse(Cache::tags(['raiding', 'events'])->has('raids:show'));
    }

    // ==================== banner ====================

    #[Group('happy-path')]
    #[Group('laravel-media-library')]
    #[Test]
    public function it_stores_an_uploaded_banner(): void
    {
        Storage::fake('public');
        $raid = Raid::factory()->create();

        UpdateRaid::run($raid, ['banner' => UploadedFile::fake()->image('karazhan.webp')]);

        $this->assertSame('karazhan.webp', $raid->fresh()->getFirstMedia(Raid::MEDIA_BANNER)?->file_name);
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function it_replaces_the_banner(): void
    {
        Storage::fake('public');
        $raid = Raid::factory()->withBanner()->create();

        UpdateRaid::run($raid, ['banner' => UploadedFile::fake()->image('new.webp')]);

        $banners = $raid->fresh()->getMedia(Raid::MEDIA_BANNER);
        $this->assertCount(1, $banners);
        $this->assertSame('new.webp', $banners->first()->file_name);
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function it_removes_the_banner_given_as_null(): void
    {
        Storage::fake('public');
        $raid = Raid::factory()->withBanner()->create();

        UpdateRaid::run($raid, ['banner' => null]);

        $this->assertFalse($raid->fresh()->hasMedia(Raid::MEDIA_BANNER));
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function it_writes_the_banner_position_to_the_banner(): void
    {
        Storage::fake('public');
        $raid = Raid::factory()->withBanner()->create();

        UpdateRaid::run($raid, ['banner_position' => 'center bottom']);

        $this->assertSame('center bottom', $raid->fresh()->banner_position);
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function it_clears_the_banner_position_given_as_null(): void
    {
        Storage::fake('public');
        $raid = Raid::factory()->withBanner('center top')->create();

        UpdateRaid::run($raid, ['banner_position' => null]);

        $this->assertNull($raid->fresh()->banner_position);
        $this->assertTrue($raid->fresh()->hasMedia(Raid::MEDIA_BANNER));
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function it_writes_the_position_onto_a_banner_uploaded_in_the_same_request(): void
    {
        Storage::fake('public');
        $raid = Raid::factory()->create();

        UpdateRaid::run($raid, [
            'banner' => UploadedFile::fake()->image('eye.webp'),
            'banner_position' => 'center bottom',
        ]);

        $this->assertSame('center bottom', $raid->fresh()->banner_position);
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function it_keeps_the_previous_position_for_a_new_banner(): void
    {
        Storage::fake('public');
        $raid = Raid::factory()->withBanner('center top')->create();

        UpdateRaid::run($raid, ['banner' => UploadedFile::fake()->image('new.webp')]);

        $this->assertSame('center top', $raid->fresh()->banner_position);
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function it_leaves_the_banner_alone_when_its_keys_are_not_given(): void
    {
        Storage::fake('public');
        $raid = Raid::factory()->withBanner('center top')->create();

        UpdateRaid::run($raid, ['name' => 'Karazhan']);

        $fresh = $raid->fresh();
        $this->assertTrue($fresh->hasMedia(Raid::MEDIA_BANNER));
        $this->assertSame('center top', $fresh->banner_position);
    }
}
