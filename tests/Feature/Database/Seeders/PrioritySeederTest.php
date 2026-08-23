<?php

namespace Tests\Feature\Database\Seeders;

use App\Contracts\HasBlizzardIcons;
use App\Http\Integrations\Blizzard\Requests\Render\FetchIconRequest;
use App\Jobs\AttachBlizzardIconToModel;
use App\Models\LootPriority;
use App\Models\PlayableClass;
use Database\Seeders\PrioritySeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

#[Group('loot')]
class PrioritySeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        Saloon::fake([
            FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        collect(PrioritySeeder::priorities())
            ->pluck('playable_class_id')
            ->filter()
            ->unique()
            ->each(fn (int $id) => PlayableClass::factory()->create(['id' => $id]));
    }

    #[Test]
    public function priority_model_implements_has_blizzard_icons(): void
    {
        $this->assertInstanceOf(HasBlizzardIcons::class, new LootPriority);
        $this->assertSame(56, LootPriority::DEFAULT_MEDIA_SIZE);
        $this->assertSame('jpg', LootPriority::DEFAULT_MEDIA_FILE_EXTENSION);
    }

    #[Test]
    public function seeder_creates_all_priorities(): void
    {
        $this->runSeeder();

        $this->assertDatabaseHas('loot_priorities', ['title' => 'Tank', 'type' => 'Role']);
        $this->assertDatabaseHas('loot_priorities', ['title' => 'Healer', 'type' => 'Role']);
        $this->assertDatabaseHas('loot_priorities', ['title' => 'Druid', 'type' => 'Class']);
        $this->assertDatabaseHas('loot_priorities', ['title' => 'Balance Druid', 'type' => 'Spec']);
        $this->assertDatabaseHas('loot_priorities', ['title' => 'Disenchant', 'type' => 'Meme']);
    }

    #[Test]
    public function seeder_attaches_blizzard_icon_to_each_priority(): void
    {
        $this->runSeeder();

        $tank = LootPriority::where('title', 'Tank')->first();

        $this->assertNotNull($tank);
        $this->assertTrue($tank->hasMedia('blizzard_icons'));
        $this->assertDatabaseHas('media', [
            'model_type' => LootPriority::class,
            'collection_name' => 'blizzard_icons',
            'file_name' => 'inv_shield_04.jpg',
        ]);

        $media = $tank->getFirstMedia('blizzard_icons');
        $this->assertSame(56, $media->getCustomProperty('size'));
        Storage::disk('public')->assertExists('blizzard-cdn/icons/56/inv_shield_04.jpg');
    }

    #[Test]
    public function seeder_is_idempotent(): void
    {
        $this->runSeeder();

        $priorityCount = LootPriority::count();
        $mediaCount = Media::count();

        $this->runSeeder();

        $this->assertSame($priorityCount, LootPriority::count());
        $this->assertSame($mediaCount, Media::count());
    }

    #[Test]
    public function seeder_does_not_reattach_icon_when_already_present(): void
    {
        $priority = LootPriority::factory()->create(['title' => 'Tank', 'type' => 'Role']);
        $priority->addMediaFromString('BINARY')
            ->usingFileName('inv_shield_04.jpg')
            ->toMediaCollection('blizzard_icons');

        $mediaCountBefore = Media::count();

        $this->runSeeder();

        $this->assertCount(1, $priority->fresh()->getMedia('blizzard_icons'));
        $this->assertSame($mediaCountBefore, LootPriority::where('title', 'Tank')->first()->getMedia('blizzard_icons')->count());
    }

    #[Test]
    public function seeder_renames_ranged_dps_to_caster_dps(): void
    {
        LootPriority::factory()->create(['title' => 'Ranged DPS', 'type' => 'Role']);

        $this->runSeeder();

        $this->assertDatabaseMissing('loot_priorities', ['title' => 'Ranged DPS']);
        $this->assertDatabaseHas('loot_priorities', ['title' => 'Caster DPS', 'type' => 'Role']);
    }

    #[Test]
    public function seeder_updates_existing_priority_without_duplicating(): void
    {
        LootPriority::factory()->create(['title' => 'Tank', 'type' => 'Role']);

        $this->runSeeder();

        $this->assertSame(1, LootPriority::where('title', 'Tank')->count());
    }

    #[Test]
    public function seeder_dispatches_retry_job_when_icon_fetch_returns_403(): void
    {
        Queue::fake();

        Saloon::fake([
            FetchIconRequest::class => MockResponse::make(
                body: ['code' => 403, 'detail' => 'Forbidden'],
                status: 403,
            ),
        ]);

        $this->runSeeder();

        $this->assertDatabaseHas('loot_priorities', ['title' => 'Tank', 'type' => 'Role']);
        $this->assertDatabaseCount('media', 0);

        Queue::assertPushed(AttachBlizzardIconToModel::class, function (AttachBlizzardIconToModel $job): bool {
            return $job->modelClass === LootPriority::class
                && (string) $job->assetUrl === 'https://render.worldofwarcraft.com/eu/icons/56/inv_shield_04.jpg';
        });
    }

    #[Test]
    public function seeder_skips_icon_and_continues_when_icon_is_not_found(): void
    {
        Queue::fake();

        Saloon::fake([
            FetchIconRequest::class => MockResponse::make(
                body: '',
                status: 404,
            ),
        ]);

        $this->runSeeder();

        $this->assertDatabaseHas('loot_priorities', ['title' => 'Tank', 'type' => 'Role']);
        $this->assertDatabaseCount('media', 0);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function seeder_sets_playable_class_id_when_defined(): void
    {
        $this->runSeeder();

        $this->assertDatabaseHas('loot_priorities', [
            'title' => 'Druid',
            'type' => 'Class',
            'playable_class_id' => 11,
        ]);

        $this->assertDatabaseHas('loot_priorities', [
            'title' => 'Balance Druid',
            'type' => 'Spec',
            'playable_class_id' => 11,
        ]);
    }

    #[Test]
    public function seeder_leaves_playable_class_id_null_when_not_defined(): void
    {
        $this->runSeeder();

        $tank = LootPriority::where('title', 'Tank')->first();
        $disenchant = LootPriority::where('title', 'Disenchant')->first();

        $this->assertNull($tank->playable_class_id);
        $this->assertNull($disenchant->playable_class_id);
    }

    // ==================== priorities ====================

    #[Test]
    public function priorities_returns_a_non_empty_array(): void
    {
        $priorities = PrioritySeeder::priorities();

        $this->assertIsArray($priorities);
        $this->assertNotEmpty($priorities);
    }

    #[Test]
    public function priorities_entries_expose_type_title_and_icon_name(): void
    {
        $priorities = PrioritySeeder::priorities();

        foreach ($priorities as $priority) {
            $this->assertArrayHasKey('type', $priority);
            $this->assertArrayHasKey('title', $priority);
            $this->assertArrayHasKey('icon_name', $priority);
        }
    }

    #[Test]
    public function priorities_includes_known_entries(): void
    {
        $priorities = PrioritySeeder::priorities();

        $this->assertContains(['type' => 'Role', 'title' => 'Tank', 'icon_name' => 'inv_shield_04'], $priorities);
        $this->assertContains(['type' => 'Meme', 'title' => 'Disenchant', 'icon_name' => 'inv_enchant_voidcrystal'], $priorities);
        $this->assertContains(['type' => 'Class', 'title' => 'Druid', 'playable_class_id' => 11, 'icon_name' => 'classicon_druid'], $priorities);
    }

    #[Test]
    public function priorities_entries_only_set_playable_class_id_for_class_spec_and_custom_types(): void
    {
        $priorities = PrioritySeeder::priorities();

        foreach ($priorities as $priority) {
            if (in_array($priority['type'], ['Class', 'Spec', 'Custom'], true)) {
                $this->assertArrayHasKey('playable_class_id', $priority, "Expected playable_class_id for '{$priority['title']}'");
            } else {
                $this->assertArrayNotHasKey('playable_class_id', $priority, "Did not expect playable_class_id for '{$priority['title']}'");
            }
        }
    }

    // ==================== helpers ====================

    private function runSeeder(): void
    {
        Model::unguarded(fn () => app(PrioritySeeder::class)->run());
    }
}
