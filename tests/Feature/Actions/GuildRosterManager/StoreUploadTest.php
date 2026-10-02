<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\StoreUpload;
use App\Jobs\ProcessGrmUpload;
use App\Models\GameVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class StoreUploadTest extends TestCase
{
    use RefreshDatabase;

    private const string CSV = "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nAlpha,Raider,70,1,Main,";

    private const array PARSED = [
        'delimiter' => ',',
        'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
        'rows' => [['Name' => 'Alpha', 'Rank' => 'Raider', 'Level' => '70', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => '']],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    #[Group('happy-path')]
    #[Test]
    public function it_saves_the_csv_as_the_game_versions_latest_upload(): void
    {
        Queue::fake([ProcessGrmUpload::class]);
        $gameVersion = GameVersion::factory()->create(['slug' => 'tbc']);
        $uploader = User::factory()->officer()->create();

        StoreUpload::run($gameVersion, self::CSV, self::PARSED, $uploader);

        $this->assertSame(self::CSV, Storage::disk('local')->get('grm/uploads/tbc/latest.csv'));
    }

    #[Test]
    public function it_replaces_the_game_versions_previous_upload(): void
    {
        Queue::fake([ProcessGrmUpload::class]);
        $gameVersion = GameVersion::factory()->create(['slug' => 'tbc']);
        $uploader = User::factory()->officer()->create();
        Storage::disk('local')->put('grm/uploads/tbc/latest.csv', 'old');

        StoreUpload::run($gameVersion, self::CSV, self::PARSED, $uploader);

        $this->assertSame(self::CSV, Storage::disk('local')->get('grm/uploads/tbc/latest.csv'));
    }

    #[Test]
    public function it_archives_a_timestamped_copy_under_the_game_versions_slug(): void
    {
        Queue::fake([ProcessGrmUpload::class]);
        $gameVersion = GameVersion::factory()->create(['slug' => 'tbc']);
        $uploader = User::factory()->officer()->create();
        $this->travelTo('2026-10-02 18:04:11');

        StoreUpload::run($gameVersion, self::CSV, self::PARSED, $uploader);

        $this->assertSame(self::CSV, Storage::disk('local')->get('grm/archives/tbc/2026-10-02_18-04-11.csv'));
    }

    #[Test]
    public function it_queues_processing_for_the_uploader_and_game_version(): void
    {
        Queue::fake([ProcessGrmUpload::class]);
        $gameVersion = GameVersion::factory()->create(['slug' => 'tbc']);
        $uploader = User::factory()->officer()->create();

        StoreUpload::run($gameVersion, self::CSV, self::PARSED, $uploader);

        Queue::assertPushed(ProcessGrmUpload::class, fn (ProcessGrmUpload $job): bool => $job->grmData === self::PARSED
            && $job->userId === $uploader->id
            && $job->gameVersionId === $gameVersion->id);
    }
}
