<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\CheckUploadFreshness;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use App\Models\GameVersion;
use App\Models\GuildRank;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class CheckUploadFreshnessTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    private const string ROSTER_ENDPOINT = '/data/wow/guild/thunderstrike/regrowth/roster';

    private const string CSV_HEADER = "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\n";

    private GameVersion $gameVersion;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->gameVersion = GameVersion::factory()->fetchableRoster()->create([
            'title' => 'Burning Crusade Anniversary',
            'slug' => 'tbc',
            'guild_name' => 'Regrowth',
        ]);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_identifies_the_game_version_it_checked(): void
    {
        $this->fakeRosters([self::ROSTER_ENDPOINT => []]);

        $result = CheckUploadFreshness::run($this->gameVersion);

        $this->assertSame(['id' => $this->gameVersion->id, 'title' => 'Burning Crusade Anniversary'], $result['gameVersion']);
    }

    #[Test]
    public function it_returns_the_uploads_last_modified_time(): void
    {
        $this->putUpload('tbc', ['Player1,Member,80,1,Main,']);
        $this->fakeRosters([self::ROSTER_ENDPOINT => []]);

        $result = CheckUploadFreshness::run($this->gameVersion);

        $this->assertEquals(
            Carbon::createFromTimestamp(Storage::disk('local')->lastModified('grm/uploads/tbc/latest.csv')),
            $result['lastModified'],
        );
    }

    // ==================== raider counts ====================

    #[Test]
    public function it_is_not_stale_when_raider_counts_match(): void
    {
        $raider = $this->rank(0, 'Raider');
        $this->putUpload('tbc', ['Player1,Raider,80,1,Main,', 'Player2,Raider,80,2,Main,', 'Player3,Raider,80,3,Main,']);
        $this->fakeRosters([self::ROSTER_ENDPOINT => $this->memberPayloads(3, $raider->sort_order)]);

        $result = CheckUploadFreshness::run($this->gameVersion);

        $this->assertSame(3, $result['blzRaiderCount']);
        $this->assertSame(3, $result['grmRaiderCount']);
        $this->assertFalse($result['dataIsStale']);
    }

    #[Test]
    public function it_is_not_stale_when_raider_counts_differ_by_less_than_three(): void
    {
        $raider = $this->rank(0, 'Raider');
        $this->putUpload('tbc', array_map(fn (int $i) => "Player{$i},Raider,80,1,Main,", range(1, 5)));
        $this->fakeRosters([self::ROSTER_ENDPOINT => $this->memberPayloads(3, $raider->sort_order)]);

        $this->assertFalse(CheckUploadFreshness::run($this->gameVersion)['dataIsStale']);
    }

    #[Test]
    public function it_is_stale_when_raider_counts_differ_by_three_or_more(): void
    {
        $raider = $this->rank(0, 'Raider');
        $this->putUpload('tbc', ['Player1,Raider,80,1,Main,', 'Player2,Raider,80,2,Main,']);
        $this->fakeRosters([self::ROSTER_ENDPOINT => $this->memberPayloads(5, $raider->sort_order)]);

        $this->assertTrue(CheckUploadFreshness::run($this->gameVersion)['dataIsStale']);
    }

    #[Test]
    public function it_flags_stale_when_no_upload_exists_but_the_roster_has_raiders(): void
    {
        $raider = $this->rank(0, 'Raider');
        $this->fakeRosters([self::ROSTER_ENDPOINT => $this->memberPayloads(5, $raider->sort_order)]);

        $result = CheckUploadFreshness::run($this->gameVersion);

        $this->assertNull($result['lastModified']);
        $this->assertSame(0, $result['grmRaiderCount']);
        $this->assertTrue($result['dataIsStale']);
    }

    #[Test]
    public function it_counts_every_rank_whose_name_contains_raider(): void
    {
        $raider = $this->rank(0, 'Raider');
        $core = $this->rank(1, 'Core Raider');
        $trial = $this->rank(2, 'Trial Raider');
        $officer = $this->rank(3, 'Officer');
        $this->putUpload('tbc', [
            'Player1,Raider,80,1,Main,',
            'Player2,Core Raider,80,2,Main,',
            'Player3,Trial Raider,80,3,Main,',
            'Player4,Officer,80,4,Main,',
        ]);
        $this->fakeRosters([self::ROSTER_ENDPOINT => [
            $this->memberPayload(1, $raider->sort_order),
            $this->memberPayload(2, $core->sort_order),
            $this->memberPayload(3, $trial->sort_order),
            $this->memberPayload(4, $officer->sort_order),
        ]]);

        $result = CheckUploadFreshness::run($this->gameVersion);

        $this->assertSame(3, $result['blzRaiderCount']);
        $this->assertSame(3, $result['grmRaiderCount']);
    }

    #[Test]
    public function it_ignores_non_raider_ranks(): void
    {
        $officer = $this->rank(0, 'Officer');
        $member = $this->rank(1, 'Member');
        $this->putUpload('tbc', ['Player1,Officer,80,1,Main,', 'Player2,Member,80,2,Main,']);
        $this->fakeRosters([self::ROSTER_ENDPOINT => [
            $this->memberPayload(1, $officer->sort_order),
            $this->memberPayload(2, $member->sort_order),
        ]]);

        $result = CheckUploadFreshness::run($this->gameVersion);

        $this->assertSame(0, $result['blzRaiderCount']);
        $this->assertSame(0, $result['grmRaiderCount']);
    }

    /**
     * str_getcsv() only splits on commas, so a semicolon-delimited row is read as one
     * column. That column still contains "Raider", so the row is counted anyway. This
     * pins the existing behaviour carried over from AddonController.
     */
    #[Test]
    public function it_counts_semicolon_delimited_rows_as_single_columns(): void
    {
        $this->putUpload('tbc', ['Player1;Raider;80;1;Main;', 'Player2;Raider;80;2;Main;'], "Name;Rank;Level;Last Online (Days);Main/Alt;Player Alts\n");
        $this->fakeRosters([self::ROSTER_ENDPOINT => []]);

        $this->assertSame(2, CheckUploadFreshness::run($this->gameVersion)['grmRaiderCount']);
    }

    // ==================== game version scoping ====================

    #[Test]
    public function it_requests_the_roster_for_the_given_game_versions_guild_realm_and_namespace(): void
    {
        $this->fakeRosters([self::ROSTER_ENDPOINT => []]);
        $expectedNamespace = $this->gameVersion->blizzard_namespace
            ->forProfileRequests($this->app->make(BlizzardConnector::class)->getRegion());

        CheckUploadFreshness::run($this->gameVersion);

        Saloon::assertSent(fn (Request $request, Response $response) => $request instanceof GetGuildRosterRequest
            && $request->resolveEndpoint() === self::ROSTER_ENDPOINT
            && $response->getPendingRequest()->headers()->get('Battlenet-Namespace') === $expectedNamespace);
    }

    #[Test]
    public function it_only_counts_raider_ranks_from_the_given_game_version(): void
    {
        $otherVersion = GameVersion::factory()->create();
        GuildRank::factory()->doesNotCountAttendance()->create(['game_version_id' => $otherVersion->id, 'sort_order' => 3, 'name' => 'Raider']);
        $this->rank(3, 'Member');
        $this->fakeRosters([self::ROSTER_ENDPOINT => $this->memberPayloads(4, 3)]);

        $this->assertSame(0, CheckUploadFreshness::run($this->gameVersion)['blzRaiderCount']);
    }

    #[Test]
    public function it_reads_only_the_given_game_versions_upload(): void
    {
        $this->putUpload('other-version', ['Player1,Raider,80,1,Main,', 'Player2,Raider,80,1,Main,']);
        Storage::disk('local')->put('grm/uploads/latest.csv', self::CSV_HEADER.'Player1,Raider,80,1,Main,');
        $this->fakeRosters([self::ROSTER_ENDPOINT => []]);

        $result = CheckUploadFreshness::run($this->gameVersion);

        $this->assertNull($result['lastModified']);
        $this->assertSame(0, $result['grmRaiderCount']);
    }

    // ==================== roster failures ====================

    #[Group('error-handling')]
    #[Test]
    public function it_reports_a_null_roster_count_and_is_not_stale_when_blizzard_fails(): void
    {
        $this->putUpload('tbc', ['Player1,Raider,80,1,Main,']);
        $this->pendingBlizzardMocks = [
            self::TOKEN_MOCK_KEY => MockResponse::make(body: self::TOKEN_MOCK_RESPONSE, status: 200),
            '*'.self::ROSTER_ENDPOINT.'*' => MockResponse::make(
                body: ['code' => 404, 'type' => 'BLZWEBAPI00000404', 'detail' => 'Not Found'],
                status: 404,
            ),
        ];
        $this->applyBlizzardMocks();

        $result = CheckUploadFreshness::run($this->gameVersion);

        $this->assertNull($result['blzRaiderCount']);
        $this->assertSame(1, $result['grmRaiderCount']);
        $this->assertFalse($result['dataIsStale']);
    }

    // ==================== helpers ====================

    /**
     * Fake one guild roster response per endpoint, matched on URL so several
     * versions' rosters can answer differently in one test.
     *
     * @param  array<string, list<array<string, mixed>>>  $membersByEndpoint
     */
    private function fakeRosters(array $membersByEndpoint): void
    {
        $this->pendingBlizzardMocks = [
            self::TOKEN_MOCK_KEY => MockResponse::make(body: self::TOKEN_MOCK_RESPONSE, status: 200),
        ];

        foreach ($membersByEndpoint as $endpoint => $members) {
            $this->pendingBlizzardMocks["*{$endpoint}*"] = MockResponse::make(body: [
                'guild' => [
                    'key' => ['href' => 'https://example.test/guild'],
                    'name' => 'Regrowth',
                    'id' => 1,
                    'realm' => ['key' => ['href' => 'https://example.test/realm'], 'name' => 'Thunderstrike', 'id' => 1, 'slug' => 'thunderstrike'],
                ],
                'members' => $members,
            ], status: 200);
        }

        $this->applyBlizzardMocks();
    }

    /**
     * Store a GRM upload for the given game version slug.
     *
     * @param  list<string>  $rows
     */
    private function putUpload(string $slug, array $rows, string $header = self::CSV_HEADER): void
    {
        Storage::disk('local')->put("grm/uploads/{$slug}/latest.csv", $header.implode("\n", $rows));
    }

    private function rank(int $sortOrder, string $name): GuildRank
    {
        return GuildRank::factory()->doesNotCountAttendance()->create([
            'game_version_id' => $this->gameVersion->id,
            'sort_order' => $sortOrder,
            'name' => $name,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function memberPayload(int $id, int $rank): array
    {
        return [
            'character' => [
                'id' => $id,
                'name' => "Player{$id}",
                'level' => 80,
                'playable_class' => ['key' => ['href' => 'https://example.test/class/1'], 'name' => 'Warrior', 'id' => 1],
                'playable_race' => ['key' => ['href' => 'https://example.test/race/1'], 'name' => 'Human', 'id' => 1],
                'realm' => ['key' => ['href' => 'https://example.test/realm/1'], 'name' => 'Thunderstrike', 'id' => 1],
            ],
            'rank' => $rank,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function memberPayloads(int $count, int $rank): array
    {
        return array_map(fn (int $id) => $this->memberPayload($id, $rank), range(1, $count));
    }
}
