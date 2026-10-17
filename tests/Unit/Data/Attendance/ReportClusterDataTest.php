<?php

namespace Tests\Unit\Data\Attendance;

use App\Data\Attendance\PlayerPresenceData;
use App\Data\Attendance\ReportClusterData;
use App\Enums\Presence;
use App\Models\Character;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class ReportClusterDataTest extends TestCase
{
    #[Test]
    public function to_array_emits_the_raid_column_shape_in_the_app_timezone(): void
    {
        $this->assertSame([
            'id' => 'r1',
            'start_time' => '2025-01-01T19:00:00+01:00',
            'zone_name' => 'Karazhan',
        ], $this->cluster()->toArray());
    }

    private function cluster(): ReportClusterData
    {
        $character = Character::factory()->withoutParents()->make(['id' => 1, 'name' => 'Thrall', 'playable_class_id' => null]);

        return new ReportClusterData(
            id: 'r1',
            code: 'ABC',
            startTime: Carbon::parse('2025-01-01 19:00', 'Europe/Paris'),
            zoneName: 'Karazhan',
            players: collect([1 => new PlayerPresenceData($character, Presence::Present)]),
        );
    }
}
