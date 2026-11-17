<?php

namespace Tests\Feature\Data\GuildRosterManager;

use App\Data\GuildRosterManager\RosterFreshnessData;
use App\Enums\GuildRosterManager\UploadOutcome;
use App\Enums\GuildRosterManager\UploadStatus;
use App\Models\DesktopClient;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\Upload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('characters')]
class RosterFreshnessDataTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    private GameVersion $gameVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gameVersion = GameVersion::factory()->fetchableRoster()->create(['slug' => 'anniversary']);
        $this->mockGetGuild(['member_count' => 1]);
        $this->applyBlizzardMocks();
    }

    // ==================== fromGameVersion ====================

    #[Test]
    public function from_game_version_computes_status_and_last_upload(): void
    {
        $accepted = Upload::factory()->for($this->gameVersion)
            ->withOutcome(UploadOutcome::Processed)
            ->create(['captured_at' => now()->subHour(), 'member_count' => 1]);
        Upload::factory()->for($this->gameVersion)->withOutcome(UploadOutcome::Superseded)->create(['captured_at' => now()]);

        $data = RosterFreshnessData::from($this->gameVersion->load(['media', 'currentRosterRevision']));

        $this->assertTrue($data->gameVersion->is($this->gameVersion));
        $this->assertSame(UploadStatus::Current, $data->status);
        $this->assertTrue($data->lastUpload?->is($accepted));
    }

    // ==================== toArray ====================

    #[Test]
    public function it_emits_camel_case_keys_with_the_icon_game_version_and_upload_resource(): void
    {
        $officer = User::factory()->create();
        $device = DesktopClient::factory()->for($officer)->create(['name' => 'Raid PC']);
        $upload = Upload::factory()->for($this->gameVersion)->for($officer)->desktop()
            ->withOutcome(UploadOutcome::Processed)
            ->create(['desktop_client_id' => $device->id, 'member_count' => 1]);

        $array = RosterFreshnessData::from($this->gameVersion->load(['media', 'currentRosterRevision']))->toArray();

        $this->assertSame(['gameVersion', 'lastUpload', 'status'], collect($array)->keys()->sort()->values()->all());
        $this->assertSame(['title', 'slug', 'color', 'icon_url'], array_keys($array['gameVersion']));
        $this->assertSame('anniversary', $array['gameVersion']['slug']);
        $this->assertSame($upload->id, $array['lastUpload']['id']);
        $this->assertSame('Raid PC', $array['lastUpload']['device']['name']);
        $this->assertSame('current', $array['status']);
    }

    #[Test]
    public function it_emits_a_null_last_upload_without_an_accepted_upload(): void
    {
        Upload::factory()->for($this->gameVersion)->withOutcome(UploadOutcome::Abandoned)->create();

        $array = RosterFreshnessData::from($this->gameVersion->load(['media', 'currentRosterRevision']))->toArray();

        $this->assertNull($array['lastUpload']);
    }
}
