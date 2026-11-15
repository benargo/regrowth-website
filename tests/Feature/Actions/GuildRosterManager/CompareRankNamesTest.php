<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\CompareRankNames;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\GuildRosterManager\RosterRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class CompareRankNamesTest extends TestCase
{
    use RefreshDatabase;

    private const array SITE_RANKS = [
        'Guild Master', 'Officer', 'Officer Alt', 'Raider', 'Champion',
        'Veteran', 'Member', 'Initiate', 'Social', 'Alt',
    ];

    private GameVersion $gameVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gameVersion = GameVersion::factory()->create();
    }

    #[Test]
    public function it_returns_nothing_when_the_names_match(): void
    {
        $this->siteRanks(self::SITE_RANKS);
        $this->gameRanks(self::SITE_RANKS);

        $this->assertSame([], CompareRankNames::run($this->gameVersion));
    }

    #[Test]
    public function it_lists_each_rank_name_that_differs(): void
    {
        $this->siteRanks(self::SITE_RANKS);
        $gameRanks = self::SITE_RANKS;
        $gameRanks[4] = 'Warden';
        $gameRanks[5] = 'Champion';
        $this->gameRanks($gameRanks);

        $this->assertSame([
            ['sortOrder' => 4, 'gameName' => 'Warden', 'siteName' => 'Champion'],
            ['sortOrder' => 5, 'gameName' => 'Champion', 'siteName' => 'Veteran'],
        ], CompareRankNames::run($this->gameVersion));
    }

    #[Test]
    public function it_lists_a_position_only_one_side_has(): void
    {
        $this->siteRanks(array_slice(self::SITE_RANKS, 0, 9));
        $this->gameRanks(self::SITE_RANKS);

        $this->assertSame([
            ['sortOrder' => 9, 'gameName' => 'Alt', 'siteName' => null],
        ], CompareRankNames::run($this->gameVersion));
    }

    #[Test]
    public function it_lists_a_position_only_the_site_has(): void
    {
        $this->siteRanks(self::SITE_RANKS);
        $this->gameRanks(array_slice(self::SITE_RANKS, 0, 9));

        $this->assertSame([
            ['sortOrder' => 9, 'gameName' => null, 'siteName' => 'Alt'],
        ], CompareRankNames::run($this->gameVersion));
    }

    #[Test]
    public function it_compares_names_exactly(): void
    {
        $this->siteRanks(['Guild Master', 'Officer']);
        $this->gameRanks(['Guild Master', 'officer']);

        $this->assertSame([
            ['sortOrder' => 1, 'gameName' => 'officer', 'siteName' => 'Officer'],
        ], CompareRankNames::run($this->gameVersion));
    }

    #[Test]
    public function it_returns_nothing_without_a_roster_revision(): void
    {
        $this->siteRanks(self::SITE_RANKS);

        $this->assertSame([], CompareRankNames::run($this->gameVersion));
    }

    #[Test]
    public function it_compares_the_latest_roster_revision(): void
    {
        $this->siteRanks(['Guild Master', 'Officer']);
        RosterRevision::factory()->for($this->gameVersion)->create(['revision' => 1, 'ranks' => ['Guild Master', 'Captain']]);
        $this->gameRanks(['Guild Master', 'Officer'], revision: 2);

        $this->assertSame([], CompareRankNames::run($this->gameVersion));
    }

    #[Test]
    public function it_never_renames_guild_ranks(): void
    {
        $this->siteRanks(self::SITE_RANKS);
        GuildRank::where('game_version_id', $this->gameVersion->id)->where('sort_order', 8)->update(['count_attendance' => false]);
        $before = $this->rankSnapshot();
        $this->gameRanks(array_reverse(self::SITE_RANKS));

        CompareRankNames::run($this->gameVersion);

        $this->assertSame($before, $this->rankSnapshot());
    }

    /**
     * @param  list<string>  $names
     */
    private function siteRanks(array $names): void
    {
        foreach ($names as $sortOrder => $name) {
            GuildRank::factory()->for($this->gameVersion)->create(['sort_order' => $sortOrder, 'name' => $name]);
        }
    }

    /**
     * @param  list<string>  $names
     */
    private function gameRanks(array $names, int $revision = 1): void
    {
        RosterRevision::factory()->for($this->gameVersion)->create(['revision' => $revision, 'ranks' => $names]);
    }

    /**
     * @return list<array{sort_order: int, name: string, count_attendance: bool}>
     */
    private function rankSnapshot(): array
    {
        return GuildRank::where('game_version_id', $this->gameVersion->id)
            ->orderBy('sort_order')
            ->get(['sort_order', 'name', 'count_attendance'])
            ->toArray();
    }
}
