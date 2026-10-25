<?php

namespace Tests\Feature\Actions\Boss;

use App\Actions\Boss\UpdateBoss;
use App\Events\RaidingDataChanged;
use App\Models\Boss;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Database\Eloquent\BroadcastableModelEventOccurred;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

#[Group('raiding')]
class UpdateBossTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    // ==================== update ====================

    #[Test]
    public function it_updates_only_the_fields_it_is_given(): void
    {
        $boss = Boss::factory()->create(['name' => 'Moroes', 'notes' => 'Vanish.']);

        UpdateBoss::run($boss, ['name' => 'Moroes the Steward']);

        $boss->refresh();
        $this->assertSame('Moroes the Steward', $boss->name);
        $this->assertSame('Vanish.', $boss->notes);
    }

    #[Test]
    public function it_clears_notes_given_as_null(): void
    {
        $boss = Boss::factory()->create(['notes' => 'Vanish.']);

        UpdateBoss::run($boss, ['notes' => null]);

        $this->assertNull($boss->fresh()->notes);
    }

    // ==================== images ====================

    #[Test]
    public function it_removes_images_by_id(): void
    {
        $boss = Boss::factory()->create();
        $kept = $this->addImage($boss, 'kept.png');
        $removed = $this->addImage($boss, 'removed.png');

        UpdateBoss::run($boss, ['removed_image_ids' => [$removed->id]]);

        $this->assertSame([$kept->id], $boss->fresh()->getMedia()->modelKeys());
        $this->assertModelMissing($removed);
    }

    #[Test]
    public function it_orders_images_by_id(): void
    {
        $boss = Boss::factory()->create();
        $first = $this->addImage($boss, 'first.png');
        $second = $this->addImage($boss, 'second.png');

        UpdateBoss::run($boss, ['image_ids' => [$second->id, $first->id]]);

        $this->assertSame([$second->id, $first->id], $boss->fresh()->getMedia()->modelKeys());
    }

    #[Test]
    public function it_removes_and_reorders_in_one_call(): void
    {
        $boss = Boss::factory()->create();
        $first = $this->addImage($boss, 'first.png');
        $second = $this->addImage($boss, 'second.png');
        $third = $this->addImage($boss, 'third.png');

        UpdateBoss::run($boss, ['removed_image_ids' => [$first->id], 'image_ids' => [$third->id, $second->id]]);

        $this->assertSame([$third->id, $second->id], $boss->fresh()->getMedia()->modelKeys());
    }

    #[Test]
    public function it_adds_uploaded_images_after_the_existing_ones(): void
    {
        $boss = Boss::factory()->create();
        $existing = $this->addImage($boss, 'existing.png');

        UpdateBoss::run($boss, ['images' => [UploadedFile::fake()->image('new.png')]]);

        $media = $boss->fresh()->getMedia();
        $this->assertCount(2, $media);
        $this->assertSame($existing->id, $media->first()->id);
        $this->assertSame('new.png', $media->last()->file_name);
    }

    // ==================== broadcasting ====================

    #[Test]
    public function it_broadcasts_the_strategy_change_when_only_images_change(): void
    {
        $boss = Boss::factory()->create();
        $first = $this->addImage($boss, 'first.png');
        $second = $this->addImage($boss, 'second.png');
        Queue::fake();

        UpdateBoss::run($boss, ['image_ids' => [$second->id, $first->id]]);

        Queue::assertPushed(
            BroadcastEvent::class,
            fn (BroadcastEvent $job): bool => $job->event instanceof BroadcastableModelEventOccurred
                && $job->event->model->is($boss)
                && $job->event->event() === 'updated',
        );
    }

    #[Test]
    public function it_does_not_broadcast_when_nothing_is_given(): void
    {
        $boss = Boss::factory()->create();
        Queue::fake();

        UpdateBoss::run($boss, []);

        Queue::assertNotPushed(BroadcastEvent::class);
    }

    #[Test]
    public function it_broadcasts_once_when_columns_and_uploads_change_together(): void
    {
        $boss = Boss::factory()->create();
        Queue::fake();

        UpdateBoss::run($boss, ['name' => 'Moroes the Steward', 'images' => [UploadedFile::fake()->image('new.png')]]);

        $this->assertCount(1, Queue::pushed(
            BroadcastEvent::class,
            fn (BroadcastEvent $job): bool => $job->event instanceof BroadcastableModelEventOccurred
                && $job->event->event() === 'updated',
        ));
        $this->assertCount(1, $boss->fresh()->getMedia());
    }

    // ==================== cache ====================

    #[Test]
    public function it_flushes_the_raiding_cache_for_a_media_only_update(): void
    {
        $boss = Boss::factory()->create();
        $first = $this->addImage($boss, 'first.png');
        $second = $this->addImage($boss, 'second.png');
        Cache::tags(['raiding', 'events'])->put('canary', 'value', 60);

        UpdateBoss::run($boss, ['image_ids' => [$second->id, $first->id]]);

        $this->assertNull(Cache::tags(['raiding', 'events'])->get('canary'));
    }

    #[Test]
    public function it_dispatches_raiding_data_changed_when_images_are_reordered(): void
    {
        $boss = Boss::factory()->create();
        $first = $this->addImage($boss, 'first.png');
        $second = $this->addImage($boss, 'second.png');
        Event::fake([RaidingDataChanged::class]);

        UpdateBoss::run($boss, ['image_ids' => [$second->id, $first->id]]);

        $this->assertSame(1, $this->dispatchedFor($boss));
    }

    #[Test]
    public function it_dispatches_raiding_data_changed_when_images_are_removed(): void
    {
        $boss = Boss::factory()->create();
        $image = $this->addImage($boss, 'first.png');
        Event::fake([RaidingDataChanged::class]);

        UpdateBoss::run($boss, ['removed_image_ids' => [$image->id]]);

        $this->assertSame(1, $this->dispatchedFor($boss));
    }

    #[Test]
    public function it_dispatches_raiding_data_changed_when_images_are_uploaded(): void
    {
        $boss = Boss::factory()->create();
        Event::fake([RaidingDataChanged::class]);

        UpdateBoss::run($boss, ['images' => [UploadedFile::fake()->image('first.png')]]);

        $this->assertSame(1, $this->dispatchedFor($boss));
    }

    // ==================== helpers ====================

    private function addImage(Boss $boss, string $name): Media
    {
        return $boss->addMedia(UploadedFile::fake()->image($name))->toMediaCollection();
    }

    private function dispatchedFor(Model $model): int
    {
        return Event::dispatched(RaidingDataChanged::class, fn (RaidingDataChanged $event): bool => $event->model->is($model))->count();
    }
}
