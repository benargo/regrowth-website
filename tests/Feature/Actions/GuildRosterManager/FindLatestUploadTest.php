<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\FindLatestUpload;
use App\Models\GameVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class FindLatestUploadTest extends TestCase
{
    use RefreshDatabase;

    private const string HEADER = 'Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    #[Test]
    public function it_returns_null_when_the_game_version_has_no_upload(): void
    {
        $gameVersion = GameVersion::factory()->create(['slug' => 'tbc']);

        $this->assertNull(FindLatestUpload::run($gameVersion));
    }

    #[Group('happy-path')]
    #[Test]
    public function it_returns_the_uploads_last_modified_time_and_member_count(): void
    {
        $gameVersion = GameVersion::factory()->create(['slug' => 'tbc']);
        $this->putUpload('tbc', self::HEADER."\nAlpha,Raider,70,1,Main,\nBravo,Member,70,2,Alt,Alpha");
        touch(Storage::disk('local')->path('grm/uploads/tbc/latest.csv'), 1790000000);

        $upload = FindLatestUpload::run($gameVersion);

        $this->assertSame(1790000000, $upload->lastModified->getTimestamp());
        // Carbon 3 defaults createFromTimestamp() to UTC; officers read times in the app timezone (Europe/Paris).
        $this->assertSame('2026-09-21 16:13:20', $upload->lastModified->toDateTimeString());
        $this->assertSame(2, $upload->memberCount);
    }

    #[Test]
    public function it_skips_blank_lines_and_handles_windows_line_endings(): void
    {
        $gameVersion = GameVersion::factory()->create(['slug' => 'tbc']);
        $this->putUpload('tbc', self::HEADER."\r\nAlpha,Raider,70,1,Main,\r\n\r\nBravo,Member,70,2,Alt,Alpha\r\n");

        $this->assertSame(2, FindLatestUpload::run($gameVersion)->memberCount);
    }

    #[Test]
    public function it_skips_header_rows_repeated_by_appended_export_chunks(): void
    {
        $gameVersion = GameVersion::factory()->create(['slug' => 'tbc']);
        $this->putUpload('tbc', self::HEADER."\nAlpha,Raider,70,1,Main,\n".self::HEADER."\nBravo,Member,70,2,Alt,Alpha");

        $this->assertSame(2, FindLatestUpload::run($gameVersion)->memberCount);
    }

    #[Test]
    public function it_ignores_other_game_versions_and_the_legacy_shared_upload(): void
    {
        $gameVersion = GameVersion::factory()->create(['slug' => 'tbc']);
        $this->putUpload('classic', self::HEADER."\nAlpha,Raider,70,1,Main,");
        Storage::disk('local')->put('grm/uploads/latest.csv', self::HEADER."\nAlpha,Raider,70,1,Main,");

        $this->assertNull(FindLatestUpload::run($gameVersion));
    }

    // ==================== helpers ====================

    private function putUpload(string $slug, string $csv): void
    {
        Storage::disk('local')->put("grm/uploads/{$slug}/latest.csv", $csv);
    }
}
