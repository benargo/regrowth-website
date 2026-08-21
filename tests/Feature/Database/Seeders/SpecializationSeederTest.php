<?php

namespace Tests\Feature\Database\Seeders;

use App\Contracts\HasBlizzardIcons;
use App\Http\Integrations\Blizzard\Requests\Render\FetchIconRequest;
use App\Jobs\AttachBlizzardIconToModel;
use App\Models\PlayableClass;
use App\Models\PlayableSpecialization;
use Database\Seeders\SpecializationSeeder;
use Illuminate\Console\Command;
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

#[Group('characters')]
class SpecializationSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        Saloon::fake([
            FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);
    }

    // ==================== record creation ====================

    #[Test]
    public function seeder_creates_all_28_specialisations(): void
    {
        $this->seedPlayableClasses();

        $this->runSeeder();

        $this->assertDatabaseCount('playable_specializations', 28);
    }

    #[Test]
    public function seeder_creates_specializations_with_correct_roles(): void
    {
        $this->seedPlayableClasses();

        $this->runSeeder();

        $druid = PlayableClass::where('name', 'Druid')->first();

        $this->assertDatabaseHas('playable_specializations', [
            'playable_class_id' => $druid->id,
            'name' => 'Balance',
            'role' => 'DPS',
        ]);

        $this->assertDatabaseHas('playable_specializations', [
            'playable_class_id' => $druid->id,
            'name' => 'Restoration',
            'role' => 'Healer',
        ]);

        $warrior = PlayableClass::where('name', 'Warrior')->first();

        $this->assertDatabaseHas('playable_specializations', [
            'playable_class_id' => $warrior->id,
            'name' => 'Protection',
            'role' => 'Tank',
        ]);
    }

    #[Test]
    public function seeder_attaches_blizzard_icon_to_each_specialization(): void
    {
        $this->seedPlayableClasses();

        $this->runSeeder();

        $this->assertDatabaseCount('media', 28);
        $this->assertDatabaseHas('media', [
            'model_type' => PlayableSpecialization::class,
            'collection_name' => 'blizzard_icons',
            'file_name' => 'spell_nature_starfall.jpg',
        ]);

        $druid = PlayableClass::where('name', 'Druid')->first();
        $balance = PlayableSpecialization::where('playable_class_id', $druid->id)->where('name', 'Balance')->first();
        $media = $balance->getFirstMedia('blizzard_icons');

        $this->assertSame(HasBlizzardIcons::DEFAULT_MEDIA_SIZE, $media->getCustomProperty('size'));
        Storage::disk('public')->assertExists('blizzard-cdn/icons/56/spell_nature_starfall.jpg');
    }

    // ==================== idempotency ====================

    #[Test]
    public function seeder_is_idempotent(): void
    {
        $this->seedPlayableClasses();

        $this->runSeeder();

        $specializationCount = PlayableSpecialization::count();
        $mediaCount = Media::count();

        $this->runSeeder();

        $this->assertSame($specializationCount, PlayableSpecialization::count());
        $this->assertSame($mediaCount, Media::count());
    }

    #[Test]
    public function seeder_does_not_reattach_icon_when_already_present(): void
    {
        $this->seedPlayableClasses();

        $druid = PlayableClass::where('name', 'Druid')->first();
        $balance = PlayableSpecialization::factory()->damage()->create([
            'playable_class_id' => $druid->id,
            'name' => 'Balance',
        ]);
        $balance->addMediaFromString('BINARY')
            ->usingFileName('spell_nature_starfall.jpg')
            ->toMediaCollection('blizzard_icons');

        $this->runSeeder();

        $this->assertSame(1, PlayableSpecialization::where('playable_class_id', $druid->id)->where('name', 'Balance')->count());
        $this->assertCount(1, $balance->fresh()->getMedia('blizzard_icons'));
    }

    // ==================== class association ====================

    #[Test]
    public function seeder_associates_specializations_with_correct_playable_class(): void
    {
        $this->seedPlayableClasses();

        $this->runSeeder();

        $shaman = PlayableClass::where('name', 'Shaman')->first();

        $shamanSpecs = PlayableSpecialization::where('playable_class_id', $shaman->id)
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['Elemental', 'Enhancement', 'Restoration'], $shamanSpecs);
    }

    // ==================== error handling ====================

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

        $this->seedPlayableClasses();

        $this->runSeeder();

        $this->assertDatabaseCount('playable_specializations', 28);
        $this->assertDatabaseCount('media', 0);

        Queue::assertPushed(AttachBlizzardIconToModel::class, function (AttachBlizzardIconToModel $job): bool {
            return $job->modelClass === PlayableSpecialization::class
                && (string) $job->assetUrl === 'https://render.worldofwarcraft.com/eu/icons/56/spell_nature_starfall.jpg';
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

        $this->seedPlayableClasses();

        $this->runSeeder();

        $this->assertDatabaseCount('playable_specializations', 28);
        $this->assertDatabaseCount('media', 0);
        Queue::assertNothingPushed();
    }

    // ==================== console output ====================

    #[Test]
    public function seeder_outputs_info_per_specialization_on_success(): void
    {
        $this->seedPlayableClasses();

        $command = $this->createMock(Command::class);
        $command->expects($this->exactly(28))
            ->method('info')
            ->with($this->matchesRegularExpression('/✓.*\[\d+\]/'));

        $this->runSeeder($command);
    }

    #[Test]
    public function seeder_outputs_warn_when_playable_class_is_not_found(): void
    {
        $command = $this->createMock(Command::class);
        $command->expects($this->exactly(9))
            ->method('warn')
            ->with($this->matchesRegularExpression('/not found — skipping/'));

        $this->runSeeder($command);
    }

    #[Test]
    public function seeder_outputs_warn_when_icon_fetch_returns_403(): void
    {
        Queue::fake();

        Saloon::fake([
            FetchIconRequest::class => MockResponse::make(
                body: ['code' => 403, 'detail' => 'Forbidden'],
                status: 403,
            ),
        ]);

        $this->seedPlayableClasses();

        $command = $this->createMock(Command::class);
        $command->expects($this->exactly(28))
            ->method('warn')
            ->with($this->matchesRegularExpression('/Icon deferred \(403\)/'));

        $this->runSeeder($command);
    }

    #[Test]
    public function seeder_outputs_warn_when_icon_is_not_found(): void
    {
        Queue::fake();

        Saloon::fake([
            FetchIconRequest::class => MockResponse::make(
                body: '',
                status: 404,
            ),
        ]);

        $this->seedPlayableClasses();

        $command = $this->createMock(Command::class);
        $command->expects($this->exactly(28))
            ->method('warn')
            ->with($this->matchesRegularExpression('/Icon skipped/'));

        $this->runSeeder($command);
    }

    private function seedPlayableClasses(): void
    {
        Model::unguarded(function () {
            PlayableClass::factory()->create(['name' => 'Druid']);
            PlayableClass::factory()->create(['name' => 'Hunter']);
            PlayableClass::factory()->create(['name' => 'Mage']);
            PlayableClass::factory()->create(['name' => 'Paladin']);
            PlayableClass::factory()->create(['name' => 'Priest']);
            PlayableClass::factory()->create(['name' => 'Rogue']);
            PlayableClass::factory()->create(['name' => 'Shaman']);
            PlayableClass::factory()->create(['name' => 'Warlock']);
            PlayableClass::factory()->create(['name' => 'Warrior']);
        });
    }

    private function runSeeder(?Command $command = null): void
    {
        Model::unguarded(function () use ($command) {
            $seeder = app(SpecializationSeeder::class);
            $seeder->setCommand($command ?? $this->createStub(Command::class));
            $seeder->run();
        });
    }
}
