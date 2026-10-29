<?php

namespace Tests\Unit\Data\GuildRosterManager;

use App\Data\GuildRosterManager\LatestUploadData;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class LatestUploadDataTest extends TestCase
{
    #[Test]
    public function it_exposes_the_constructor_values(): void
    {
        $lastModified = Carbon::parse('2026-10-01 18:30:00', 'UTC');

        $dto = new LatestUploadData(lastModified: $lastModified, memberCount: 142);

        $this->assertSame($lastModified, $dto->lastModified);
        $this->assertSame(142, $dto->memberCount);
    }

    #[Test]
    public function it_maps_snake_case_input_and_casts_the_date_to_carbon(): void
    {
        $dto = LatestUploadData::from([
            'last_modified' => '2026-10-01T18:30:00+00:00',
            'member_count' => 142,
        ]);

        $this->assertInstanceOf(Carbon::class, $dto->lastModified);
        $this->assertTrue($dto->lastModified->equalTo(Carbon::parse('2026-10-01 18:30:00', 'UTC')));
        $this->assertSame(142, $dto->memberCount);
    }

    #[Test]
    public function it_serialises_to_snake_case_keys_with_an_atom_date(): void
    {
        $dto = new LatestUploadData(
            lastModified: Carbon::parse('2026-10-01 18:30:00', 'UTC'),
            memberCount: 142,
        );

        $this->assertSame([
            'last_modified' => '2026-10-01T18:30:00+00:00',
            'member_count' => 142,
        ], $dto->toArray());
    }
}
