<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\CheckUploadStatus;
use App\Enums\GuildRosterManager\UploadStatus;
use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRequest;
use App\Models\GameVersion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class CheckUploadStatusTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    private const int UPLOADED_AT = 1790000000;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    // ==================== upload presence ====================

    #[Test]
    public function it_is_missing_when_the_game_version_has_no_upload(): void
    {
        $gameVersion = $this->makeGameVersion();
        $this->fakeGuild(memberCount: 10);

        $this->assertSame(UploadStatus::Missing, CheckUploadStatus::run($gameVersion));
        Saloon::assertNotSent(GetGuildRequest::class);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_is_current_when_the_member_counts_match_and_the_upload_is_recent(): void
    {
        $gameVersion = $this->makeGameVersion();
        $this->putUpload(members: 10);
        $this->fakeGuild(memberCount: 10);

        $this->assertSame(UploadStatus::Current, CheckUploadStatus::run($gameVersion));
    }

    // ==================== member count ====================

    /**
     * @return array<string, array{int, UploadStatus}>
     */
    public static function memberCountDifferences(): array
    {
        return [
            'guild one under the threshold below' => [8, UploadStatus::Current],
            'guild one under the threshold above' => [12, UploadStatus::Current],
            'guild at the threshold below' => [7, UploadStatus::Stale],
            'guild at the threshold above' => [13, UploadStatus::Stale],
        ];
    }

    #[DataProvider('memberCountDifferences')]
    #[Test]
    public function it_compares_the_guild_member_count_with_the_upload_in_both_directions(int $guildMembers, UploadStatus $expected): void
    {
        $gameVersion = $this->makeGameVersion();
        $this->putUpload(members: 10);
        $this->fakeGuild(memberCount: $guildMembers);

        $this->assertSame($expected, CheckUploadStatus::run($gameVersion));
    }

    // ==================== upload age ====================

    #[Test]
    public function it_is_current_when_the_upload_is_exactly_a_week_old(): void
    {
        $gameVersion = $this->makeGameVersion();
        $this->putUpload(members: 10);
        $this->fakeGuild(memberCount: 10);
        $this->travelToUploadAge(seconds: 7 * 24 * 60 * 60);

        $this->assertSame(UploadStatus::Current, CheckUploadStatus::run($gameVersion));
    }

    #[Test]
    public function it_is_outdated_when_the_upload_is_a_second_over_a_week_old(): void
    {
        $gameVersion = $this->makeGameVersion();
        $this->putUpload(members: 10);
        $this->fakeGuild(memberCount: 10);
        $this->travelToUploadAge(seconds: 7 * 24 * 60 * 60 + 1);

        $this->assertSame(UploadStatus::Outdated, CheckUploadStatus::run($gameVersion));
    }

    #[Test]
    public function it_prefers_stale_over_outdated(): void
    {
        $gameVersion = $this->makeGameVersion();
        $this->putUpload(members: 10);
        $this->fakeGuild(memberCount: 20);
        $this->travelToUploadAge(seconds: 8 * 24 * 60 * 60);

        $this->assertSame(UploadStatus::Stale, CheckUploadStatus::run($gameVersion));
    }

    // ==================== blizzard failures ====================

    #[Group('error-handling')]
    #[Test]
    public function it_is_unknown_when_blizzard_fails_and_the_upload_is_recent(): void
    {
        $gameVersion = $this->makeGameVersion();
        $this->putUpload(members: 10);
        $this->fakeGuildNotFound();

        $this->assertSame(UploadStatus::Unknown, CheckUploadStatus::run($gameVersion));
        // CountGuildMembers swallows a missing fake too, so prove the faked 404 is what produced Unknown.
        Saloon::assertSent(GetGuildRequest::class);
    }

    #[Group('error-handling')]
    #[Test]
    public function it_is_outdated_when_blizzard_fails_and_the_upload_is_old(): void
    {
        $gameVersion = $this->makeGameVersion();
        $this->putUpload(members: 10);
        $this->fakeGuildNotFound();
        $this->travelToUploadAge(seconds: 8 * 24 * 60 * 60);

        $this->assertSame(UploadStatus::Outdated, CheckUploadStatus::run($gameVersion));
        Saloon::assertSent(GetGuildRequest::class);
    }

    // ==================== helpers ====================

    private function makeGameVersion(): GameVersion
    {
        return GameVersion::factory()->fetchableRoster()->create(['slug' => 'tbc']);
    }

    /**
     * Write the upload with a fixed modification time and set "now" an hour
     * after it, so every test starts from a recent upload.
     */
    private function putUpload(int $members): void
    {
        $rows = collect(range(1, $members))->map(fn (int $i): string => "Player{$i},Member,70,1,Main,");

        Storage::disk('local')->put(
            'grm/uploads/tbc/latest.csv',
            "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\n{$rows->implode("\n")}",
        );
        touch(Storage::disk('local')->path('grm/uploads/tbc/latest.csv'), self::UPLOADED_AT);

        $this->travelToUploadAge(seconds: 60 * 60);
    }

    private function travelToUploadAge(int $seconds): void
    {
        $this->travelTo(Carbon::createFromTimestamp(self::UPLOADED_AT + $seconds));
    }

    private function fakeGuild(int $memberCount): void
    {
        $this->mockGetGuild(['member_count' => $memberCount]);
        $this->applyBlizzardMocks();
    }

    private function fakeGuildNotFound(): void
    {
        $this->pendingBlizzardMocks = [
            self::TOKEN_MOCK_KEY => MockResponse::make(body: self::TOKEN_MOCK_RESPONSE, status: 200),
            GetGuildRequest::class => MockResponse::make(
                body: ['code' => 404, 'type' => 'BLZWEBAPI00000404', 'detail' => 'Not Found'],
                status: 404,
            ),
        ];
        $this->applyBlizzardMocks();
    }
}
