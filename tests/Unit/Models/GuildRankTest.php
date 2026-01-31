<?php

namespace Tests\Unit\Models;

use App\Models\GuildRank;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class GuildRankTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return GuildRank::class;
    }

    #[Test]
    public function it_uses_guild_ranks_table(): void
    {
        $model = new GuildRank;

        $this->assertSame('guild_ranks', $model->getTable());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new GuildRank;

        $this->assertFillable($model, [
            'position',
            'name',
        ]);
    }

    #[Test]
    public function it_uses_auto_incrementing_primary_key(): void
    {
        $model = new GuildRank;

        $this->assertSame('id', $model->getKeyName());
        $this->assertTrue($model->getIncrementing());
    }

    #[Test]
    public function it_can_create_a_guild_rank(): void
    {
        $guildRank = $this->create([
            'position' => 0,
            'name' => 'Guild Master',
        ]);

        $this->assertTableHas([
            'position' => 0,
            'name' => 'Guild Master',
        ]);
        $this->assertModelExists($guildRank);
    }

    #[Test]
    public function it_can_mass_assign_position_and_name(): void
    {
        $guildRank = GuildRank::create([
            'position' => 1,
            'name' => 'Officer',
        ]);

        $this->assertSame(1, $guildRank->position);
        $this->assertSame('Officer', $guildRank->name);
    }

    #[Test]
    public function it_enforces_unique_position_constraint(): void
    {
        $this->create(['position' => 0, 'name' => 'Guild Master']);

        $this->assertUniqueConstraint(function () {
            $this->create(['position' => 0, 'name' => 'Duplicate Position']);
        });
    }

    #[Test]
    public function it_allows_same_name_with_different_positions(): void
    {
        $this->create(['position' => 0, 'name' => 'Officer']);
        $secondRank = $this->create(['position' => 1, 'name' => 'Officer']);

        $this->assertModelExists($secondRank);
        $this->assertDatabaseCount('guild_ranks', 2);
    }

    #[Test]
    public function it_has_timestamps(): void
    {
        $guildRank = $this->create();

        $this->assertNotNull($guildRank->created_at);
        $this->assertNotNull($guildRank->updated_at);
    }

    #[Test]
    public function it_converts_name_to_title_case(): void
    {
        $guildRank = $this->create([
            'position' => 0,
            'name' => 'guild master',
        ]);

        $this->assertSame('Guild Master', $guildRank->name);
    }

    #[Test]
    public function it_converts_uppercase_name_to_title_case(): void
    {
        $guildRank = $this->create([
            'position' => 1,
            'name' => 'OFFICER',
        ]);

        $this->assertSame('Officer', $guildRank->name);
    }

    #[Test]
    public function it_converts_mixed_case_name_to_title_case(): void
    {
        $guildRank = $this->create([
            'position' => 2,
            'name' => 'sEnIoR rAiDeR',
        ]);

        $this->assertSame('Senior Raider', $guildRank->name);
    }
}
