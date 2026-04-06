<?php

namespace Tests\Unit\Services\Blizzard\Data;

use App\Models\GuildRank;
use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\Data\GuildMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GuildMemberTest extends TestCase
{
    use RefreshDatabase;

    private function sampleMemberData(int $rank = 3): array
    {
        return [
            'character' => [
                'id' => 12345,
                'name' => 'Thrall',
                'level' => 80,
                'realm' => [
                    'id' => 1,
                    'slug' => 'thunderstrike',
                ],
                'playable_class' => [
                    'id' => 7,
                    'name' => 'Shaman',
                ],
                'playable_race' => [
                    'id' => 2,
                    'name' => 'Orc',
                ],
                'faction' => [
                    'type' => 'HORDE',
                ],
            ],
            'rank' => $rank,
        ];
    }

    #[Test]
    public function it_creates_from_array_with_guild_rank_model(): void
    {
        $guildRank = GuildRank::factory()->create(['position' => 3]);

        $member = GuildMember::fromArray($this->sampleMemberData(3));

        $this->assertInstanceOf(GuildRank::class, $member->rank);
        $this->assertSame($guildRank->id, $member->rank->id);
    }

    #[Test]
    public function it_creates_from_array_with_int_rank_when_no_model_exists(): void
    {
        $member = GuildMember::fromArray($this->sampleMemberData(99));

        $this->assertIsInt($member->rank);
        $this->assertSame(99, $member->rank);
    }

    #[Test]
    public function it_stores_character_data(): void
    {
        $data = $this->sampleMemberData();
        $member = GuildMember::fromArray($data);

        $this->assertSame(12345, $member->character['id']);
        $this->assertSame('Thrall', $member->character['name']);
    }

    #[Test]
    public function it_converts_to_array_with_guild_rank_model(): void
    {
        $guildRank = GuildRank::factory()->create(['position' => 3, 'name' => 'Raider']);

        $member = GuildMember::fromArray($this->sampleMemberData(3));
        $result = $member->toArray();

        $this->assertIsArray($result['rank']);
        $this->assertSame($guildRank->id, $result['rank']['id']);
        $this->assertSame(3, $result['rank']['position']);
        $this->assertSame('Raider', $result['rank']['name']);
    }

    #[Test]
    public function it_converts_to_array_with_int_rank(): void
    {
        $member = GuildMember::fromArray($this->sampleMemberData(99));
        $result = $member->toArray();

        $this->assertSame(99, $result['rank']);
    }

    #[Test]
    public function it_formats_character_data_in_to_array(): void
    {
        $member = GuildMember::fromArray($this->sampleMemberData(99));
        $result = $member->toArray();

        $this->assertSame(12345, $result['character']['id']);
        $this->assertSame('Thrall', $result['character']['name']);
        $this->assertSame(80, $result['character']['level']);
        $this->assertSame('thunderstrike', $result['character']['realm']['slug']);
    }

    #[Test]
    public function it_formats_faction_as_ucfirst_lower(): void
    {
        $member = GuildMember::fromArray($this->sampleMemberData(99));
        $result = $member->toArray();

        $this->assertSame('Horde', $result['character']['faction']);
    }

    #[Test]
    public function it_loads_playable_class_relation(): void
    {
        $this->mock(BlizzardService::class, function ($mock) {
            $mock->shouldReceive('findPlayableClass')
                ->with(7)
                ->once()
                ->andReturn(['id' => 7, 'name' => 'Shaman']);
        });

        $member = GuildMember::fromArray($this->sampleMemberData(99));
        $member->with('character.playable_class');

        $this->assertSame(['id' => 7, 'name' => 'Shaman'], $member->character['playable_class']);
    }

    #[Test]
    public function it_loads_playable_race_relation(): void
    {
        $this->mock(BlizzardService::class, function ($mock) {
            $mock->shouldReceive('findPlayableRace')
                ->with(2)
                ->once()
                ->andReturn(['id' => 2, 'name' => 'Orc']);
        });

        $member = GuildMember::fromArray($this->sampleMemberData(99));
        $member->with('character.playable_race');

        $this->assertSame(['id' => 2, 'name' => 'Orc'], $member->character['playable_race']);
    }

    #[Test]
    public function it_loads_rank_relation(): void
    {
        $guildRank = GuildRank::factory()->create(['position' => 99]);

        $member = new GuildMember(
            character: $this->sampleMemberData(99)['character'],
            rank: 99,
        );

        $member->with('rank');

        $this->assertInstanceOf(GuildRank::class, $member->rank);
        $this->assertSame($guildRank->id, $member->rank->id);
    }

    #[Test]
    public function with_returns_self_for_chaining(): void
    {
        $this->mock(BlizzardService::class, function ($mock) {
            $mock->shouldReceive('findPlayableClass')->andReturn(['id' => 7, 'name' => 'Shaman']);
        });

        $member = GuildMember::fromArray($this->sampleMemberData(99));
        $result = $member->with('character.playable_class');

        $this->assertSame($member, $result);
    }
}
