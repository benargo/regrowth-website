<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\GuildRankResource;
use App\Models\GuildRank;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GuildRankResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $rank = GuildRank::factory()->create();

        $array = (new GuildRankResource($rank))->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('position', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('count_attendance', $array);
    }

    #[Test]
    public function it_returns_correct_scalar_fields(): void
    {
        $rank = GuildRank::factory()->create([
            'position' => 3,
            'name' => 'Officer',
        ]);

        $array = (new GuildRankResource($rank))->toArray(new Request);

        $this->assertSame($rank->id, $array['id']);
        $this->assertSame(3, $array['position']);
        $this->assertSame('Officer', $array['name']);
        $this->assertTrue($array['count_attendance']);
    }

    #[Test]
    public function it_returns_false_for_count_attendance_when_rank_does_not_count(): void
    {
        $rank = GuildRank::factory()->doesNotCountAttendance()->create();

        $array = (new GuildRankResource($rank))->toArray(new Request);

        $this->assertFalse($array['count_attendance']);
    }
}
