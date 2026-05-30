<?php

namespace Tests\Feature\Database\Seeders;

use App\Http\Integrations\Blizzard\Requests\Render\FetchAssetRequest;
use App\Models\LootCouncil\Priority;
use Database\Seeders\PrioritySeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class PrioritySeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        Saloon::fake([
            FetchAssetRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);
    }

    private function runSeeder(): void
    {
        Model::unguarded(fn () => app(PrioritySeeder::class)->run());
    }

    #[Test]
    public function seeder_creates_all_priorities(): void
    {
        $this->runSeeder();

        $this->assertDatabaseHas('lootcouncil_priorities', ['title' => 'Tank', 'type' => 'Role']);
        $this->assertDatabaseHas('lootcouncil_priorities', ['title' => 'Healer', 'type' => 'Role']);
        $this->assertDatabaseHas('lootcouncil_priorities', ['title' => 'Druid', 'type' => 'Class']);
        $this->assertDatabaseHas('lootcouncil_priorities', ['title' => 'Balance Druid', 'type' => 'Spec']);
        $this->assertDatabaseHas('lootcouncil_priorities', ['title' => 'Disenchant', 'type' => 'Meme']);
    }

    #[Test]
    public function seeder_attaches_blizzard_icon_to_each_priority(): void
    {
        $this->runSeeder();

        $tank = Priority::where('title', 'Tank')->first();

        $this->assertNotNull($tank);
        $this->assertTrue($tank->hasMedia('blizzard_icons'));
        $this->assertDatabaseHas('media', [
            'model_type' => Priority::class,
            'collection_name' => 'blizzard_icons',
            'file_name' => 'inv_shield_04.jpg',
        ]);
    }

    #[Test]
    public function seeder_is_idempotent(): void
    {
        $this->runSeeder();

        $priorityCount = Priority::count();
        $mediaCount = Media::count();

        $this->runSeeder();

        $this->assertSame($priorityCount, Priority::count());
        $this->assertSame($mediaCount, Media::count());
    }

    #[Test]
    public function seeder_does_not_reattach_icon_when_already_present(): void
    {
        $priority = Priority::factory()->create(['title' => 'Tank', 'type' => 'Role']);
        $priority->addMediaFromString('BINARY')
            ->usingFileName('inv_shield_04.jpg')
            ->toMediaCollection('blizzard_icons');

        $mediaCountBefore = Media::count();

        $this->runSeeder();

        $this->assertCount(1, $priority->fresh()->getMedia('blizzard_icons'));
        $this->assertSame($mediaCountBefore, Priority::where('title', 'Tank')->first()->getMedia('blizzard_icons')->count());
    }

    #[Test]
    public function seeder_renames_ranged_dps_to_caster_dps(): void
    {
        Priority::factory()->create(['title' => 'Ranged DPS', 'type' => 'Role']);

        $this->runSeeder();

        $this->assertDatabaseMissing('lootcouncil_priorities', ['title' => 'Ranged DPS']);
        $this->assertDatabaseHas('lootcouncil_priorities', ['title' => 'Caster DPS', 'type' => 'Role']);
    }

    #[Test]
    public function seeder_updates_existing_priority_without_duplicating(): void
    {
        Priority::factory()->create(['title' => 'Tank', 'type' => 'Role']);

        $this->runSeeder();

        $this->assertSame(1, Priority::where('title', 'Tank')->count());
    }
}
