<?php

namespace Tests\Unit\Services\Attendance;

use App\Models\Character;
use App\Models\GuildRank;
use App\Services\Attendance\AttendanceMatrixData;
use App\Services\Attendance\CharacterAttendanceRowData;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use JsonSerializable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttendanceMatrixDataTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{id: string, code: string|null, dayOfWeek: string, date: string, zoneName: string|null} */
    protected function makeRaid(string $id = 'raid-1', ?string $code = 'ABC', string $dayOfWeek = 'Wednesday', string $date = '2024-01-10', ?string $zoneName = 'Naxxramas'): array
    {
        return [
            'id' => $id,
            'code' => $code,
            'dayOfWeek' => $dayOfWeek,
            'date' => $date,
            'zoneName' => $zoneName,
        ];
    }

    protected function makeRow(): CharacterAttendanceRowData
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);

        return new CharacterAttendanceRowData(
            character: $character,
            percentage: 100.0,
            attendance: [1],
            plannedAbsences: [null],
        );
    }

    protected function makeData(array $raids = [], array $rows = []): AttendanceMatrixData
    {
        return new AttendanceMatrixData(
            raids: collect($raids),
            rows: collect($rows),
        );
    }

    #[Test]
    public function it_exposes_typed_properties(): void
    {
        $raid = $this->makeRaid();
        $row = $this->makeRow();
        $data = $this->makeData([$raid], [$row]);

        $this->assertInstanceOf(Collection::class, $data->raids);
        $this->assertInstanceOf(Collection::class, $data->rows);
        $this->assertCount(1, $data->raids);
        $this->assertCount(1, $data->rows);
    }

    #[Test]
    public function it_implements_arrayable_and_json_serializable(): void
    {
        $data = $this->makeData();

        $this->assertInstanceOf(Arrayable::class, $data);
        $this->assertInstanceOf(JsonSerializable::class, $data);
    }

    #[Test]
    public function to_array_returns_raids_and_rows_keys(): void
    {
        $data = $this->makeData();
        $array = $data->toArray();

        $this->assertArrayHasKey('raids', $array);
        $this->assertArrayHasKey('rows', $array);
    }

    #[Test]
    public function to_array_serializes_raids_as_plain_arrays(): void
    {
        $raid = $this->makeRaid('raid-99', 'XYZ', 'Friday', '2024-03-15', 'Ulduar');
        $data = $this->makeData([$raid]);

        $raids = $data->toArray()['raids'];

        $this->assertCount(1, $raids);
        $this->assertSame('raid-99', $raids[0]['id']);
        $this->assertSame('XYZ', $raids[0]['code']);
        $this->assertSame('Friday', $raids[0]['dayOfWeek']);
        $this->assertSame('2024-03-15', $raids[0]['date']);
        $this->assertSame('Ulduar', $raids[0]['zoneName']);
    }

    #[Test]
    public function to_array_serializes_raids_with_null_optional_fields(): void
    {
        $raid = $this->makeRaid('raid-1', null, 'Monday', '2024-01-01', null);
        $data = $this->makeData([$raid]);

        $raids = $data->toArray()['raids'];

        $this->assertNull($raids[0]['code']);
        $this->assertNull($raids[0]['zoneName']);
    }

    #[Test]
    public function to_array_serializes_rows_via_character_attendance_row_to_array(): void
    {
        $row = $this->makeRow();
        $data = $this->makeData([], [$row]);

        $rows = $data->toArray()['rows'];

        $this->assertCount(1, $rows);
        $this->assertSame($row->toArray(), $rows[0]);
    }

    #[Test]
    public function to_array_reindexes_raids_when_collection_has_offset_keys(): void
    {
        $raids = collect([$this->makeRaid('r1'), $this->makeRaid('r2')]);

        $data = new AttendanceMatrixData(
            raids: $raids->slice(1),
            rows: collect(),
        );

        $array = $data->toArray();

        $this->assertSame(0, array_key_first($array['raids']));
    }

    #[Test]
    public function to_array_handles_empty_raids_and_rows(): void
    {
        $data = $this->makeData();
        $array = $data->toArray();

        $this->assertSame([], $array['raids']);
        $this->assertSame([], $array['rows']);
    }

    #[Test]
    public function json_serialize_matches_to_array(): void
    {
        $raid = $this->makeRaid();
        $row = $this->makeRow();
        $data = $this->makeData([$raid], [$row]);

        $this->assertSame($data->toArray(), $data->jsonSerialize());
    }
}
