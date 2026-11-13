<?php

namespace Tests\Unit\Casts;

use App\Casts\AsRosterEntryData;
use App\Data\GuildRosterManager\BanData;
use App\Data\GuildRosterManager\MemberData;
use App\Enums\GuildRosterManager\RosterEntryKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class AsRosterEntryDataTest extends TestCase
{
    #[Test]
    #[DataProvider('kinds')]
    public function it_hydrates_each_kind_as_its_data_class(RosterEntryKind $kind, int $vector): void
    {
        $result = $this->read($this->storedPayload($vector), ['kind' => $kind->value, 'epoch' => 0]);

        $this->assertInstanceOf($kind->dataClass(), $result);
    }

    #[Test]
    public function it_fills_epoch_from_the_epoch_attribute(): void
    {
        $result = $this->read($this->storedPayload(0), ['kind' => 'member', 'epoch' => 1700000000]);

        $this->assertSame(1700000000, $result->epoch);
    }

    #[Test]
    public function it_reads_a_ban_without_a_reason(): void
    {
        $payload = json_encode(Arr::except($this->vectors()[5]['row'], ['epoch', 'reason']));

        $result = $this->read($payload, ['kind' => 'ban', 'epoch' => 0]);

        $this->assertInstanceOf(BanData::class, $result);
        $this->assertNull($result->reason);
    }

    #[Test]
    public function it_returns_null_for_a_null_payload(): void
    {
        $this->assertNull($this->read(null, ['kind' => 'member', 'epoch' => 0]));
    }

    #[Test]
    #[Group('error-handling')]
    public function it_refuses_to_read_without_the_kind_attribute(): void
    {
        $this->expectException(LogicException::class);

        $this->read($this->storedPayload(0), ['epoch' => 0]);
    }

    #[Test]
    public function it_serialises_without_epoch(): void
    {
        $member = MemberData::from($this->vectors()[0]['row']);

        $result = (new AsRosterEntryData)->set($this->createStub(Model::class), 'payload', $member, []);

        $this->assertSame(Arr::except($this->vectors()[0]['row'], ['epoch']), json_decode($result, true));
    }

    #[Test]
    public function it_passes_null_through_on_set(): void
    {
        $this->assertNull((new AsRosterEntryData)->set($this->createStub(Model::class), 'payload', null, []));
    }

    #[Test]
    #[Group('error-handling')]
    public function it_refuses_a_plain_array(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new AsRosterEntryData)->set($this->createStub(Model::class), 'payload', $this->vectors()[0]['row'], []);
    }

    #[Test]
    public function it_serialises_with_extra_exclusions(): void
    {
        $ban = BanData::from($this->vectors()[5]['row']);

        $result = json_decode(AsRosterEntryData::serialize($ban, ['reason']), true);

        $this->assertArrayNotHasKey('reason', $result);
        $this->assertArrayNotHasKey('epoch', $result);
        $this->assertSame('Bad Apple', $result['name']);
    }

    /**
     * @return array<string, array{RosterEntryKind, int}>
     */
    public static function kinds(): array
    {
        return [
            'member' => [RosterEntryKind::Member, 0],
            'leaver' => [RosterEntryKind::Leaver, 2],
            'alt group' => [RosterEntryKind::AltGroup, 3],
            'ban' => [RosterEntryKind::Ban, 5],
        ];
    }

    // ==================== helpers ====================

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function read(?string $payload, array $attributes): mixed
    {
        return (new AsRosterEntryData)->get($this->createStub(Model::class), 'payload', $payload, [...$attributes, 'payload' => $payload]);
    }

    private function storedPayload(int $vector): string
    {
        return json_encode(Arr::except($this->vectors()[$vector]['row'], ['epoch']));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function vectors(): array
    {
        return json_decode(
            file_get_contents(base_path('tests/Fixtures/GuildRosterManager/fingerprints.json')),
            true,
        )['vectors'];
    }
}
