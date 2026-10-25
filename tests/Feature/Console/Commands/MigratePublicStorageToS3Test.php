<?php

namespace Tests\Feature\Console\Commands;

use App\Http\Integrations\Blizzard\Requests\Render\FetchIconRequest;
use App\Jobs\Discord\AttachAvatarToUser;
use App\Jobs\Discord\RefreshUserAvatars;
use App\Models\Character;
use App\Models\PlayableClass;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

#[Group('media')]
class MigratePublicStorageToS3Test extends TestCase
{
    use RefreshDatabase;

    private const string QUESTIONMARK = 'blizzard-cdn/icons/56/inv_misc_questionmark.jpg';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('s3');
        Queue::fake([RefreshUserAvatars::class, AttachAvatarToUser::class]);
        Saloon::fake([
            FetchIconRequest::class => MockResponse::make(body: 'missing', status: 404),
        ]);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_moves_a_media_row_with_its_conversions(): void
    {
        $media = $this->raidBanner();
        $original = $media->getPathRelativeToRoot();
        $header = $media->getPathRelativeToRoot(Raid::MEDIA_CONVERSION_HEADER);

        $this->artisan('app:migrate-public-storage-to-s3')->assertExitCode(0);

        Storage::disk('s3')->assertExists([$original, $header]);
        Storage::disk('public')->assertExists([$original, $header]);
        $this->assertDatabaseHas('media', ['id' => $media->id, 'disk' => 's3', 'conversions_disk' => 's3']);
    }

    #[Test]
    public function it_respects_custom_path_generators(): void
    {
        $media = $this->classIcon('class_mage.jpg');

        $this->artisan('app:migrate-public-storage-to-s3')->assertExitCode(0);

        Storage::disk('s3')->assertExists('blizzard-cdn/icons/56/class_mage.jpg');
        $this->assertSame(
            Storage::disk('s3')->url('blizzard-cdn/icons/56/class_mage.jpg'),
            Media::find($media->id)->getUrl(),
        );
    }

    #[Test]
    public function it_repaths_legacy_doubled_icon_file_names(): void
    {
        $media = $this->classIcon('inv_bracer_02.jpg', 'ICONBYTES');
        $this->doubleOnPublic('blizzard-cdn/icons/56/', 'inv_bracer_02.jpg');

        $this->artisan('app:migrate-public-storage-to-s3')->assertExitCode(0);

        $this->assertSame('ICONBYTES', Storage::disk('s3')->get('blizzard-cdn/icons/56/inv_bracer_02.jpg'));
        Storage::disk('s3')->assertMissing('blizzard-cdn/icons/56/inv_bracer_02.jpginv_bracer_02.jpg');
        $this->assertDatabaseHas('media', ['id' => $media->id, 'disk' => 's3']);
    }

    #[Test]
    public function it_repaths_legacy_doubled_portrait_file_names(): void
    {
        $media = Character::factory()->create()
            ->addMediaFromString('PORTRAITBYTES')
            ->usingFileName('portrait.jpg')
            ->toMediaCollection('character_portraits', 'public');
        $this->doubleOnPublic('blizzard-cdn/characters/thunderstrike/135/', 'portrait.jpg');

        $this->artisan('app:migrate-public-storage-to-s3')->assertExitCode(0);

        $this->assertSame('PORTRAITBYTES', Storage::disk('s3')->get('blizzard-cdn/characters/thunderstrike/135/portrait.jpg'));
        Storage::disk('s3')->assertMissing('blizzard-cdn/characters/thunderstrike/135/portrait.jpgportrait.jpg');
        $this->assertDatabaseHas('media', ['id' => $media->id, 'disk' => 's3']);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_leaves_a_row_on_public_when_a_file_is_missing(): void
    {
        $media = $this->raidBanner();
        Storage::disk('public')->delete($media->getPathRelativeToRoot(Raid::MEDIA_CONVERSION_HEADER));

        $this->artisan('app:migrate-public-storage-to-s3')
            ->expectsOutputToContain("Media {$media->id}")
            ->assertExitCode(1);

        $this->assertDatabaseHas('media', ['id' => $media->id, 'disk' => 'public', 'conversions_disk' => 'public']);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_leaves_a_row_on_public_when_a_write_fails(): void
    {
        $media = $this->raidBanner();

        $fake = Storage::disk('s3');
        $stub = Mockery::mock(FilesystemAdapter::class, [$fake->getDriver(), $fake->getAdapter(), $fake->getConfig()])->makePartial();
        $stub->shouldReceive('writeStream')->andReturnFalse();
        Storage::set('s3', $stub);

        $this->artisan('app:migrate-public-storage-to-s3')->assertExitCode(1);

        $this->assertDatabaseHas('media', ['id' => $media->id, 'disk' => 'public', 'conversions_disk' => 'public']);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_skips_a_row_whose_conversion_is_no_longer_registered(): void
    {
        $stale = $this->raidBanner();
        Media::whereKey($stale->id)->update(['generated_conversions' => json_encode(['gone' => true])]);
        $valid = $this->classIcon('class_mage.jpg');

        $this->artisan('app:migrate-public-storage-to-s3')
            ->expectsOutputToContain("Media {$stale->id}")
            ->assertExitCode(1);

        $this->assertDatabaseHas('media', ['id' => $stale->id, 'disk' => 'public']);
        $this->assertDatabaseHas('media', ['id' => $valid->id, 'disk' => 's3']);
    }

    #[Test]
    public function it_is_idempotent(): void
    {
        $this->raidBanner();
        Storage::disk('public')->put('images/existing.webp', 'PUBLIC');
        Storage::disk('s3')->put('images/existing.webp', 'S3BYTES');
        Storage::disk('public')->put('images/loose.webp', 'LOOSE');

        $this->artisan('app:migrate-public-storage-to-s3')->assertExitCode(0);

        $this->artisan('app:migrate-public-storage-to-s3')
            ->expectsTable(['Metric', 'Count'], [
                ['Rows moved', 0],
                ['Rows failed', 0],
                ['Files copied', 0],
                ['Files failed', 0],
            ])
            ->assertExitCode(0);

        $this->assertSame('S3BYTES', Storage::disk('s3')->get('images/existing.webp'));
        $this->assertSame('LOOSE', Storage::disk('s3')->get('images/loose.webp'));
    }

    // ==================== loose files ====================

    #[Test]
    public function it_copies_mirror_and_loose_files_but_not_orphans(): void
    {
        $public = Storage::disk('public');
        $public->put('blizzard-cdn/characters/x.jpg', 'X');
        $public->put('images/assignments_blueprint.webp', 'BLUEPRINT');
        $public->put('blizzard/old.jpg', 'OLD');
        $public->put('999/orphan.jpg', 'ORPHAN');
        $public->put('blizzard-cdn/icons/56/y.jpgy.jpg', 'Y');

        $this->artisan('app:migrate-public-storage-to-s3')->assertExitCode(0);

        $s3 = Storage::disk('s3');
        $this->assertSame('X', $s3->get('blizzard-cdn/characters/x.jpg'));
        $this->assertSame('BLUEPRINT', $s3->get('images/assignments_blueprint.webp'));
        $s3->assertMissing(['blizzard/old.jpg', '999/orphan.jpg', 'blizzard-cdn/icons/56/y.jpgy.jpg']);
    }

    // ==================== questionmark ====================

    #[Test]
    public function it_ensures_the_questionmark_from_public(): void
    {
        Storage::disk('public')->put(self::QUESTIONMARK, 'PUBLICQ');

        $this->artisan('app:migrate-public-storage-to-s3')->assertExitCode(0);

        $this->assertSame('PUBLICQ', Storage::disk('s3')->get(self::QUESTIONMARK));
        Saloon::assertNothingSent();
    }

    #[Test]
    public function it_ensures_the_questionmark_by_fetching_it(): void
    {
        Saloon::fake([
            FetchIconRequest::class => MockResponse::make(body: 'FETCHED', status: 200),
        ]);

        $this->artisan('app:migrate-public-storage-to-s3')->assertExitCode(0);

        $this->assertSame('FETCHED', Storage::disk('s3')->get(self::QUESTIONMARK));
    }

    #[Test]
    #[Group('error-handling')]
    public function it_ensures_the_questionmark_from_the_bundled_file_when_the_fetch_fails(): void
    {
        $this->artisan('app:migrate-public-storage-to-s3')->assertExitCode(0);

        Saloon::assertSent(FetchIconRequest::class);
        $this->assertSame(
            file_get_contents(resource_path('images/inv_misc_questionmark.jpg')),
            Storage::disk('s3')->get(self::QUESTIONMARK),
        );
    }

    #[Test]
    #[Group('error-handling')]
    public function it_fails_when_writing_the_bundled_questionmark_fails(): void
    {
        $fake = Storage::disk('s3');
        $stub = Mockery::mock(FilesystemAdapter::class, [$fake->getDriver(), $fake->getAdapter(), $fake->getConfig()])->makePartial();
        $stub->shouldReceive('put')->andReturnFalse();
        Storage::set('s3', $stub);

        $this->artisan('app:migrate-public-storage-to-s3')
            ->expectsTable(['Metric', 'Count'], [
                ['Rows moved', 0],
                ['Rows failed', 0],
                ['Files copied', 0],
                ['Files failed', 1],
            ])
            ->assertExitCode(1);

        Storage::disk('s3')->assertMissing(self::QUESTIONMARK);
    }

    // ==================== avatars ====================

    #[Test]
    public function it_dispatches_the_avatar_backfill(): void
    {
        $this->artisan('app:migrate-public-storage-to-s3')->assertExitCode(0);

        Queue::assertPushed(RefreshUserAvatars::class);
    }

    // ==================== dry run ====================

    #[Test]
    public function it_writes_and_dispatches_nothing_on_a_dry_run(): void
    {
        $media = $this->raidBanner();
        Storage::disk('public')->put('images/loose.webp', 'LOOSE');
        User::factory()->create(['avatar' => 'a_hash', 'guild_avatar' => null]);
        User::factory()->create(['avatar' => null, 'guild_avatar' => 'g_hash']);
        User::factory()->create(['avatar' => null, 'guild_avatar' => null]);
        Queue::fake([RefreshUserAvatars::class, AttachAvatarToUser::class]);

        $this->artisan('app:migrate-public-storage-to-s3 --dry-run')
            ->expectsTable(['Metric', 'Count'], [
                ['Rows that would move', 1],
                ['Rows that would fail', 0],
                ['Files that would be copied', 1],
                ['Files that would fail', 0],
                ['Users with avatar hashes', 2],
            ])
            ->assertExitCode(0);

        $this->assertSame([], Storage::disk('s3')->allFiles());
        $this->assertDatabaseHas('media', ['id' => $media->id, 'disk' => 'public', 'conversions_disk' => 'public']);
        Queue::assertNothingPushed();
        Saloon::assertNothingSent();
    }

    // ==================== media disk ====================

    #[Test]
    #[Group('error-handling')]
    public function it_fails_fast_when_the_media_disk_is_not_the_target_disk(): void
    {
        $media = $this->raidBanner();
        Storage::disk('public')->put('images/loose.webp', 'LOOSE');
        config(['media-library.disk_name' => 'public']);

        $this->artisan('app:migrate-public-storage-to-s3')
            ->expectsOutputToContain('media-library.disk_name')
            ->assertExitCode(1);

        $this->assertSame([], Storage::disk('s3')->allFiles());
        $this->assertDatabaseHas('media', ['id' => $media->id, 'disk' => 'public', 'conversions_disk' => 'public']);
        Queue::assertNothingPushed();
        Saloon::assertNothingSent();
    }

    // ==================== helpers ====================

    private function raidBanner(): Media
    {
        return Raid::factory()->create()
            ->addMedia(UploadedFile::fake()->image('banner.png', 64, 36))
            ->toMediaCollection(Raid::MEDIA_BANNER, 'public');
    }

    private function classIcon(string $fileName, string $contents = 'ICON'): Media
    {
        return PlayableClass::factory()->create()
            ->addMediaFromString($contents)
            ->usingFileName($fileName)
            ->toMediaCollection('blizzard_icons', 'public');
    }

    /**
     * Move a stored original on the public disk to its legacy doubled file name.
     */
    private function doubleOnPublic(string $directory, string $fileName): void
    {
        Storage::disk('public')->move($directory.$fileName, $directory.$fileName.$fileName);
    }
}
