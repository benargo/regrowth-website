<?php

namespace Tests\Unit\Http\Resources\GuildRosterManager;

use App\Enums\GuildRosterManager\UploadOutcome;
use App\Enums\GuildRosterManager\UploadSource;
use App\Http\Resources\GameVersionResource;
use App\Http\Resources\GuildRosterManager\UploadResource;
use App\Models\DesktopClient;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\Upload;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class UploadResourceTest extends TestCase
{
    #[Test]
    #[Group('contract')]
    public function it_returns_the_upload_shape_without_relations(): void
    {
        $upload = $this->makeUpload();

        $this->assertSame([
            'errorCount' => 1,
            'id' => '0198c8f0-0000-7000-8000-000000000001',
            'outcome' => UploadOutcome::Processed,
            'processedCount' => 40,
            'skippedCount' => 3,
            'source' => UploadSource::Desktop,
            'startedAt' => '2026-11-14T19:30:00+01:00',
            'warningCount' => 2,
        ], (new UploadResource($upload))->resolve(new Request));
    }

    #[Test]
    public function it_returns_a_null_start_time_when_the_upload_has_no_capture_time(): void
    {
        $upload = $this->makeUpload(['captured_at' => null]);

        $this->assertNull((new UploadResource($upload))->resolve(new Request)['startedAt']);
    }

    #[Test]
    public function it_returns_the_device_name_when_the_device_is_loaded(): void
    {
        $upload = $this->makeUpload();
        $upload->setRelation('device', (new DesktopClient)->forceFill(['name' => 'Raid PC']));

        $this->assertSame(['name' => 'Raid PC'], (new UploadResource($upload))->resolve(new Request)['device']);
    }

    #[Test]
    public function it_returns_a_null_device_when_the_loaded_device_is_missing(): void
    {
        $upload = $this->makeUpload();
        $upload->setRelation('device', null);

        $array = (new UploadResource($upload))->resolve(new Request);

        $this->assertArrayHasKey('device', $array);
        $this->assertNull($array['device']);
    }

    #[Test]
    public function it_returns_the_uploader_display_name_when_the_user_is_loaded(): void
    {
        $upload = $this->makeUpload();
        $upload->setRelation('user', (new User)->forceFill(['username' => 'thrall', 'nickname' => 'Warchief']));

        $this->assertSame(['name' => 'Warchief'], (new UploadResource($upload))->resolve(new Request)['uploader']);
    }

    #[Test]
    public function it_returns_a_null_uploader_when_the_loaded_user_is_missing(): void
    {
        $upload = $this->makeUpload();
        $upload->setRelation('user', null);

        $array = (new UploadResource($upload))->resolve(new Request);

        $this->assertArrayHasKey('uploader', $array);
        $this->assertNull($array['uploader']);
    }

    #[Test]
    #[Group('resource')]
    public function it_returns_the_game_version_in_the_switcher_scope_when_loaded(): void
    {
        $upload = $this->makeUpload();
        $upload->setRelation('gameVersion', GameVersion::factory()->make([
            'id' => 7,
            'title' => 'Forever',
            'slug' => 'forever',
            'color' => null,
            'release_date' => null,
        ]));

        $gameVersion = (new UploadResource($upload))->resolve(new Request)['gameVersion'];

        $this->assertInstanceOf(GameVersionResource::class, $gameVersion);
        $this->assertSame(
            ['id' => 7, 'title' => 'Forever', 'slug' => 'forever', 'color' => null, 'release_date' => null],
            $gameVersion->resolve(new Request),
        );
    }

    // ==================== helpers ====================

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeUpload(array $attributes = []): Upload
    {
        return (new Upload)->forceFill([
            'id' => '0198c8f0-0000-7000-8000-000000000001',
            'source' => UploadSource::Desktop,
            'outcome' => UploadOutcome::Processed,
            'captured_at' => CarbonImmutable::parse('2026-11-14 19:30:00', 'Europe/Paris'),
            'processed_count' => 40,
            'skipped_count' => 3,
            'warning_count' => 2,
            'error_count' => 1,
            ...$attributes,
        ]);
    }
}
