<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\ComputeFingerprint;
use App\Data\GuildRosterManager\BanData;
use App\Data\GuildRosterManager\MemberData;
use App\Data\GuildRosterManager\RanksData;
use App\Enums\GuildRosterManager\RosterEntryKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class ComputeFingerprintTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function vectors(): array
    {
        $vectors = json_decode(
            file_get_contents(__DIR__.'/../../../Fixtures/GuildRosterManager/fingerprints.json'),
            true,
        )['vectors'];

        $cases = [];

        foreach ($vectors as $index => $vector) {
            $cases["{$vector['kind']} #{$index}"] = [$vector];
        }

        return $cases;
    }

    /**
     * @param  array<string, mixed>  $vector
     */
    #[Test]
    #[DataProvider('vectors')]
    public function it_matches_the_shared_test_vector(array $vector): void
    {
        $data = $vector['kind'] === 'ranks'
            ? new RanksData($vector['row'])
            : RosterEntryKind::from($vector['kind'])->dataClass()::from($vector['row']);

        $this->assertSame($vector['fingerprint'], hash('sha256', $vector['canonical']));
        $this->assertSame($vector['fingerprint'], ComputeFingerprint::run($data));
    }

    #[Test]
    public function it_ignores_input_key_order(): void
    {
        $row = self::vectors()['member #0'][0]['row'];

        $shuffled = array_reverse($row, true);

        $this->assertSame(
            ComputeFingerprint::run(MemberData::from($row)),
            ComputeFingerprint::run(MemberData::from($shuffled)),
        );
    }

    #[Test]
    public function it_changes_when_history_order_changes(): void
    {
        $row = self::vectors()['leaver #2'][0]['row'];
        $reordered = $row;
        $reordered['history'] = array_reverse($row['history']);

        $kind = RosterEntryKind::Leaver->dataClass();

        $this->assertNotSame(
            ComputeFingerprint::run($kind::from($row)),
            ComputeFingerprint::run($kind::from($reordered)),
        );
    }

    #[Test]
    public function it_ignores_epoch(): void
    {
        $row = self::vectors()['member #0'][0]['row'];
        $later = $row;
        $later['epoch'] = $row['epoch'] + 500;

        $this->assertSame(
            ComputeFingerprint::run(MemberData::from($row)),
            ComputeFingerprint::run(MemberData::from($later)),
        );
    }

    #[Test]
    public function it_changes_when_a_ban_is_unbanned(): void
    {
        $row = self::vectors()['ban #5'][0]['row'];
        $unbanned = $row;
        $unbanned['unbanned'] = true;

        $this->assertNotSame(
            ComputeFingerprint::run(BanData::from($row)),
            ComputeFingerprint::run(BanData::from($unbanned)),
        );
    }
}
