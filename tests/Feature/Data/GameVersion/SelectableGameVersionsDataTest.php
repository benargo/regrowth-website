<?php

namespace Tests\Feature\Data\GameVersion;

use App\Data\GameVersion\SelectableGameVersionsData;
use App\Models\GameVersion;
use App\Models\WarcraftLogs\Guild;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class SelectableGameVersionsDataTest extends TestCase
{
    use RefreshDatabase;

    // ==================== fromGameVersions ====================

    #[Test]
    public function from_game_versions_carries_raw_version_media_and_guild_attributes(): void
    {
        Storage::fake('public');
        $guild = Guild::factory()->create();
        $withIcon = GameVersion::factory()->forGuild($guild)->create();
        $withIcon->addMedia(UploadedFile::fake()->image('icon.png'))->toMediaCollection(GameVersion::MEDIA_ICON);
        $sharingGuild = GameVersion::factory()->forGuild($guild)->create();
        $withoutGuild = GameVersion::factory()->create(['warcraft_logs_guild_id' => null]);

        $data = SelectableGameVersionsData::fromGameVersions(
            GameVersion::with(['media', 'warcraftLogsGuild'])->orderBy('id')->get(),
        );

        $this->assertSame([$withIcon->id, $sharingGuild->id, $withoutGuild->id], array_column($data->versions, 'id'));
        $this->assertEquals([$withIcon->id], array_column($data->media, 'model_id'));
        $this->assertSame([$guild->id], array_column($data->guilds, 'id'));
    }

    // ==================== toArray ====================

    #[Test]
    public function it_round_trips_through_a_cache_that_disallows_objects(): void
    {
        Storage::fake('public');
        $gameVersion = GameVersion::factory()->forGuild()->create();
        $gameVersion->addMedia(UploadedFile::fake()->image('icon.png'))->toMediaCollection(GameVersion::MEDIA_ICON);
        $data = SelectableGameVersionsData::fromGameVersions(GameVersion::with(['media', 'warcraftLogsGuild'])->get());

        $cached = unserialize(serialize($data->toArray()), ['allowed_classes' => false]);
        $rebuilt = SelectableGameVersionsData::from($cached);

        $this->assertSame($data->versions, $rebuilt->versions);
        $this->assertSame($data->media, $rebuilt->media);
        $this->assertSame($data->guilds, $rebuilt->guilds);
    }
}
