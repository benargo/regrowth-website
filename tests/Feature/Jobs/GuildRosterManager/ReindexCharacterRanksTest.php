<?php

namespace Tests\Feature\Jobs\GuildRosterManager;

use App\Data\GuildRosterManager\LeaverData;
use App\Data\GuildRosterManager\MemberData;
use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Enums\RankSource;
use App\Jobs\GuildRosterManager\ReindexCharacterRanks;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\GuildRosterManager\RosterEntry;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

#[Group('characters')]
class ReindexCharacterRanksTest extends TestCase
{
    use RefreshDatabase;

    private const int EPOCH = 1785657600;

    private const string MIRA = 'Player-5826-0A1B2C3D';

    private const string PAN = 'Player-5826-0A1B2C3E';

    private GameVersion $version;

    /** @var Collection<int, GuildRank> */
    private Collection $ranks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-16 12:00:00'));

        $this->version = GameVersion::factory()->create();

        foreach (range(0, 4) as $sortOrder) {
            GuildRank::factory()->for($this->version)->create(['sort_order' => $sortOrder]);
        }

        $this->ranks = $this->version->guildRanks()->get()->keyBy('sort_order');
    }

    // ==================== rank reindexing ====================

    #[Test]
    public function it_sets_each_members_rank_from_their_rank_index(): void
    {
        $character = $this->character(self::MIRA, rank: null);
        $this->memberEntry(self::MIRA, rankIndex: 3);

        $this->runJob();

        $character->refresh();
        $this->assertSame($this->ranks[3]->id, $character->rank_id);
        $this->assertSame(self::EPOCH, $character->rank_observed_at->getTimestamp());
        $this->assertSame(RankSource::Grm, $character->rank_source);
    }

    #[Test]
    public function it_leaves_a_character_whose_stored_rank_evidence_is_newer(): void
    {
        $character = $this->character(self::MIRA, rank: 1, observedAt: Carbon::createFromTimestampUTC(self::EPOCH + 60));
        $this->memberEntry(self::MIRA, rankIndex: 3);

        $this->runJob();

        $this->assertSame($this->ranks[1]->id, $character->fresh()->rank_id);
        $this->assertSame(RankSource::Blizzard, $character->fresh()->rank_source);
    }

    #[Test]
    public function it_repairs_a_cleared_rank_at_equal_evidence(): void
    {
        $character = $this->character(self::MIRA, rank: null, observedAt: Carbon::createFromTimestampUTC(self::EPOCH));
        $this->memberEntry(self::MIRA, rankIndex: 2);

        $this->runJob();

        $this->assertSame($this->ranks[2]->id, $character->fresh()->rank_id);
    }

    #[Test]
    public function it_repairs_a_cleared_rank_despite_newer_stored_evidence(): void
    {
        $character = $this->character(self::MIRA, rank: null, observedAt: Carbon::createFromTimestampUTC(self::EPOCH + 60));
        $this->memberEntry(self::MIRA, rankIndex: 2);

        $this->runJob();

        $character->refresh();
        $this->assertSame($this->ranks[2]->id, $character->rank_id);
        $this->assertSame(self::EPOCH, $character->rank_observed_at->getTimestamp());
        $this->assertSame(RankSource::Grm, $character->rank_source);
    }

    #[Test]
    public function it_skips_a_character_already_at_that_rank(): void
    {
        $observedAt = Carbon::createFromTimestampUTC(self::EPOCH - 3600);
        $character = $this->character(self::MIRA, rank: 3, observedAt: $observedAt);
        $this->memberEntry(self::MIRA, rankIndex: 3);

        $this->runJob();

        $this->assertTrue($observedAt->equalTo($character->fresh()->rank_observed_at));
    }

    #[Test]
    public function it_skips_a_rank_index_without_a_rank_and_a_guid_without_a_character(): void
    {
        $character = $this->character(self::MIRA, rank: 1);
        $this->memberEntry(self::MIRA, rankIndex: 7);
        $this->memberEntry(self::PAN, rankIndex: 2);

        $this->runJob();

        $this->assertSame($this->ranks[1]->id, $character->fresh()->rank_id);
    }

    #[Test]
    public function it_ignores_leaver_entries_and_other_game_versions(): void
    {
        $leaver = $this->character(self::MIRA, rank: null);
        $this->leaverEntry(self::MIRA, rankIndex: 2);
        $other = GameVersion::factory()->create();
        $this->memberEntry(self::PAN, rankIndex: 2, version: $other);
        $elsewhere = Character::factory()->withGrmGuid(self::PAN)->create(['game_version_id' => $other->id, 'rank_id' => null]);

        $this->runJob();

        $this->assertNull($leaver->fresh()->rank_id);
        $this->assertNull($elsewhere->fresh()->rank_id);
    }

    #[Test]
    public function it_records_no_observation_time_for_a_zero_epoch(): void
    {
        $character = $this->character(self::MIRA, rank: null);
        $this->memberEntry(self::MIRA, rankIndex: 2, epoch: 0);

        $this->runJob();

        $this->assertNull($character->fresh()->rank_observed_at);
        $this->assertSame($this->ranks[2]->id, $character->fresh()->rank_id);
    }

    // ==================== cache invalidation ====================

    #[Test]
    public function it_flushes_the_attendance_and_characters_caches_when_a_rank_changed(): void
    {
        $this->character(self::MIRA, rank: null);
        $this->memberEntry(self::MIRA, rankIndex: 3);
        Cache::tags(['attendance'])->put('probe', true);
        Cache::tags(['characters'])->put('probe', true);

        $this->runJob();

        $this->assertFalse(Cache::tags(['attendance'])->has('probe'));
        $this->assertFalse(Cache::tags(['characters'])->has('probe'));
    }

    #[Test]
    public function it_keeps_the_caches_when_no_rank_changed(): void
    {
        $this->character(self::MIRA, rank: 3);
        $this->memberEntry(self::MIRA, rankIndex: 3);
        Cache::tags(['attendance'])->put('probe', true);
        Cache::tags(['characters'])->put('probe', true);

        $this->runJob();

        $this->assertTrue(Cache::tags(['attendance'])->has('probe'));
        $this->assertTrue(Cache::tags(['characters'])->has('probe'));
    }

    // ==================== job contract ====================

    #[Test]
    #[Group('contract')]
    public function it_declares_its_queue_contract(): void
    {
        $job = new ReindexCharacterRanks($this->version->id);
        $class = new ReflectionClass(ReindexCharacterRanks::class);

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertSame(0, $class->getAttributes(Tries::class)[0]->newInstance()->tries);
        $this->assertSame(3, $class->getAttributes(MaxExceptions::class)[0]->newInstance()->maxExceptions);
        $this->assertSame(60, $class->getAttributes(Backoff::class)[0]->newInstance()->backoff);
        $this->assertSame(900, $class->getAttributes(Timeout::class)[0]->newInstance()->timeout);
        $this->assertTrue(now()->addHours(6)->equalTo($job->retryUntil()));
        $this->assertSame(['grm-sync', "game-version:{$this->version->id}"], $job->tags());

        $middleware = $job->middleware();
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
        $this->assertSame("grm-sync:{$this->version->id}", $middleware[0]->key);
        $this->assertTrue($middleware[0]->shareKey);
        $this->assertSame(60, $middleware[0]->releaseAfter);
        $this->assertSame(960, $middleware[0]->expiresAfter);
    }

    // ==================== helpers ====================

    private function runJob(): void
    {
        (new ReindexCharacterRanks($this->version->id))->withFakeQueueInteractions()->handle();
    }

    private function memberEntry(string $guid, int $rankIndex, int $epoch = self::EPOCH, ?GameVersion $version = null): RosterEntry
    {
        return RosterEntry::factory()->create([
            'game_version_id' => ($version ?? $this->version)->id,
            'kind' => RosterEntryKind::Member,
            'key' => $guid,
            'payload' => MemberData::from([
                'guid' => $guid,
                'name' => 'Mira Ashvale',
                'rankIndex' => $rankIndex,
                'level' => 60,
                'class' => 'WARLOCK',
                'race' => 'Scourge',
                'altGroup' => null,
                'history' => [['type' => 'joined', 'date' => '2026-08-02']],
                'epoch' => $epoch,
            ]),
            'epoch' => $epoch,
            'revision' => 1,
        ]);
    }

    private function leaverEntry(string $guid, int $rankIndex): RosterEntry
    {
        return RosterEntry::factory()->create([
            'game_version_id' => $this->version->id,
            'kind' => RosterEntryKind::Leaver,
            'key' => $guid,
            'payload' => LeaverData::from([
                'guid' => $guid,
                'name' => 'Mira Ashvale',
                'rankIndex' => $rankIndex,
                'level' => 60,
                'class' => 'WARLOCK',
                'race' => 'Scourge',
                'history' => [['type' => 'joined', 'date' => '2026-08-02'], ['type' => 'left', 'date' => '2026-10-01']],
                'epoch' => self::EPOCH,
            ]),
            'epoch' => self::EPOCH,
            'revision' => 1,
        ]);
    }

    private function character(string $guid, ?int $rank, ?CarbonInterface $observedAt = null): Character
    {
        $factory = Character::factory()->withGrmGuid($guid);

        if ($observedAt !== null) {
            $factory = $factory->rankObserved($observedAt->copy()->setTimezone(config('app.timezone')), RankSource::Blizzard);
        }

        return $factory->create([
            'game_version_id' => $this->version->id,
            'rank_id' => $rank === null ? null : $this->ranks[$rank]->id,
        ]);
    }
}
