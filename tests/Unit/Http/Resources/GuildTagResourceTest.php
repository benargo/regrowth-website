<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\GuildTagResource;
use App\Models\GuildTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class GuildTagResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $guildTag = GuildTag::factory()->create();

        $array = (new GuildTagResource($guildTag))->resolve(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('count_attendance', $array);
    }

    #[Test]
    public function it_returns_correct_id(): void
    {
        $guildTag = GuildTag::factory()->create();

        $array = (new GuildTagResource($guildTag))->resolve(new Request);

        $this->assertSame($guildTag->id, $array['id']);
    }

    #[Test]
    public function it_returns_correct_name(): void
    {
        $guildTag = GuildTag::factory()->create(['name' => 'Fury']);

        $array = (new GuildTagResource($guildTag))->resolve(new Request);

        $this->assertSame('Fury', $array['name']);
    }

    #[Test]
    public function it_returns_true_when_counting_attendance(): void
    {
        $guildTag = GuildTag::factory()->countsAttendance()->create();

        $array = (new GuildTagResource($guildTag))->resolve(new Request);

        $this->assertTrue($array['count_attendance']);
    }

    #[Test]
    public function it_returns_false_when_not_counting_attendance(): void
    {
        $guildTag = GuildTag::factory()->doesNotCountAttendance()->create();

        $array = (new GuildTagResource($guildTag))->resolve(new Request);

        $this->assertFalse($array['count_attendance']);
    }

    #[Test]
    public function it_does_not_expose_extra_keys(): void
    {
        $guildTag = GuildTag::factory()->create();
        $guildTag->load('phase');

        $array = (new GuildTagResource($guildTag))->resolve(new Request);

        $this->assertSame(['id', 'name', 'count_attendance', 'phaseNumber'], array_keys($array));
    }

    #[Test]
    public function it_includes_phase_number_when_phase_loaded(): void
    {
        $guildTag = GuildTag::factory()->withPhase()->create();
        $guildTag->load('phase');

        $array = (new GuildTagResource($guildTag))->resolve(new Request);

        $this->assertArrayHasKey('phaseNumber', $array);
        $this->assertSame($guildTag->phase->number, $array['phaseNumber']);
    }

    #[Test]
    public function it_returns_null_phase_number_when_tag_has_no_phase(): void
    {
        $guildTag = GuildTag::factory()->withoutPhase()->create();
        $guildTag->load('phase');

        $array = (new GuildTagResource($guildTag))->resolve(new Request);

        $this->assertArrayHasKey('phaseNumber', $array);
        $this->assertNull($array['phaseNumber']);
    }

    #[Test]
    public function it_excludes_phase_number_when_phase_not_loaded(): void
    {
        $guildTag = GuildTag::factory()->withPhase()->create();

        $array = (new GuildTagResource($guildTag))->resolve(new Request);

        $this->assertArrayNotHasKey('phaseNumber', $array);
    }
}
