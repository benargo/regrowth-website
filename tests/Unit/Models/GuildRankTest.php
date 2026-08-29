<?php

namespace Tests\Unit\Models;

use App\Models\Character;
use App\Models\GuildRank;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\EloquentSortable\Sortable;
use Tests\Support\ModelTestCase;

#[Group('characters')]
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
            'sort_order',
            'name',
            'count_attendance',
        ]);
    }

    #[Test]
    public function it_declares_fillable_via_attribute(): void
    {
        $model = new GuildRank;

        $this->assertFillableAttribute($model, [
            'sort_order',
            'name',
            'count_attendance',
        ]);
    }

    #[Test]
    public function it_has_expected_casts(): void
    {
        $model = new GuildRank;

        $this->assertCasts($model, [
            'sort_order' => 'integer',
            'count_attendance' => 'boolean',
        ]);
    }

    #[Test]
    public function it_uses_auto_incrementing_primary_key(): void
    {
        $model = new GuildRank;

        $this->assertSame('id', $model->getKeyName());
        $this->assertTrue($model->getIncrementing());
    }

    // ==================== sorting ====================

    #[Test]
    public function it_implements_sortable(): void
    {
        $this->assertInstanceOf(Sortable::class, new GuildRank);
    }

    #[Test]
    public function it_assigns_zero_based_sort_order_on_create(): void
    {
        $first = GuildRank::create(['name' => 'Guild Master']);
        $second = GuildRank::create(['name' => 'Officer']);

        $this->assertSame(0, $first->sort_order);
        $this->assertSame(1, $second->sort_order);
    }

    #[Test]
    public function it_keeps_explicit_sort_order_on_create(): void
    {
        $guildRank = GuildRank::create(['name' => 'Raider', 'sort_order' => 5]);

        $this->assertSame(5, $guildRank->sort_order);
        $this->assertDatabaseHas('guild_ranks', ['name' => 'Raider', 'sort_order' => 5]);
    }

    #[Test]
    public function ordered_scope_sorts_by_sort_order(): void
    {
        $this->create(['sort_order' => 2, 'name' => 'Raider']);
        $this->create(['sort_order' => 0, 'name' => 'Guild Master']);
        $this->create(['sort_order' => 1, 'name' => 'Officer']);

        $this->assertSame(
            [0, 1, 2],
            GuildRank::ordered()->pluck('sort_order')->all(),
        );
    }

    #[Test]
    public function it_allows_duplicate_sort_order(): void
    {
        $this->create(['sort_order' => 0, 'name' => 'Guild Master']);
        $duplicate = $this->create(['sort_order' => 0, 'name' => 'Officer']);

        $this->assertModelExists($duplicate);
        $this->assertDatabaseCount('guild_ranks', 2);
    }

    // ==================== persistence ====================

    #[Test]
    public function it_can_create_a_guild_rank(): void
    {
        $guildRank = $this->create([
            'sort_order' => 0,
            'name' => 'Guild Master',
        ]);

        $this->assertTableHas([
            'sort_order' => 0,
            'name' => 'Guild Master',
        ]);
        $this->assertModelExists($guildRank);
    }

    #[Test]
    public function it_can_mass_assign_sort_order_and_name(): void
    {
        $guildRank = GuildRank::create([
            'sort_order' => 1,
            'name' => 'Officer',
        ]);

        $this->assertSame(1, $guildRank->sort_order);
        $this->assertSame('Officer', $guildRank->name);
    }

    // ==================== count attendance ====================

    #[Test]
    public function count_attendance_defaults_to_true(): void
    {
        $guildRank = $this->create(['sort_order' => 0, 'name' => 'Raider']);

        $this->assertTrue($guildRank->count_attendance);
    }

    #[Test]
    public function count_attendance_can_be_set_to_false(): void
    {
        $guildRank = $this->create([
            'sort_order' => 0,
            'name' => 'Social',
            'count_attendance' => false,
        ]);

        $this->assertFalse($guildRank->count_attendance);
    }

    // ==================== name constraints ====================

    #[Test]
    public function it_allows_same_name_with_different_sort_orders(): void
    {
        $this->create(['sort_order' => 0, 'name' => 'Officer']);
        $secondRank = $this->create(['sort_order' => 1, 'name' => 'Officer']);

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

    // ==================== name casing ====================

    #[Test]
    public function it_converts_name_to_title_case(): void
    {
        $guildRank = $this->create([
            'sort_order' => 0,
            'name' => 'guild master',
        ]);

        $this->assertSame('Guild Master', $guildRank->name);
    }

    #[Test]
    public function it_converts_uppercase_name_to_title_case(): void
    {
        $guildRank = $this->create([
            'sort_order' => 1,
            'name' => 'OFFICER',
        ]);

        $this->assertSame('Officer', $guildRank->name);
    }

    #[Test]
    public function it_converts_mixed_case_name_to_title_case(): void
    {
        $guildRank = $this->create([
            'sort_order' => 2,
            'name' => 'sEnIoR rAiDeR',
        ]);

        $this->assertSame('Senior Raider', $guildRank->name);
    }

    // ==================== characters relationship ====================

    #[Test]
    public function characters_returns_has_many_relationship(): void
    {
        $guildRank = new GuildRank;

        $this->assertInstanceOf(HasMany::class, $guildRank->characters());
    }

    #[Test]
    public function characters_returns_associated_characters(): void
    {
        $guildRank = $this->create(['sort_order' => 0, 'name' => 'Officer']);
        $character1 = Character::factory()->create(['rank_id' => $guildRank->id]);
        $character2 = Character::factory()->create(['rank_id' => $guildRank->id]);

        $this->assertCount(2, $guildRank->characters);
        $this->assertTrue($guildRank->characters->contains($character1));
        $this->assertTrue($guildRank->characters->contains($character2));
    }

    #[Test]
    public function characters_returns_empty_collection_when_no_characters_exist(): void
    {
        $guildRank = $this->create(['sort_order' => 0, 'name' => 'Officer']);

        $this->assertCount(0, $guildRank->characters);
    }

    // ==================== main characters relationship ====================

    #[Test]
    public function main_characters_returns_has_many_relationship(): void
    {
        $guildRank = new GuildRank;

        $this->assertInstanceOf(HasMany::class, $guildRank->mainCharacters());
    }

    #[Test]
    public function main_characters_only_returns_characters_where_is_main_is_true(): void
    {
        $guildRank = $this->create(['sort_order' => 0, 'name' => 'Officer']);
        $mainChar = Character::factory()->main()->create(['rank_id' => $guildRank->id]);
        $altChar = Character::factory()->create(['rank_id' => $guildRank->id, 'is_main' => false]);

        $this->assertCount(1, $guildRank->mainCharacters);
        $this->assertTrue($guildRank->mainCharacters->contains($mainChar));
        $this->assertFalse($guildRank->mainCharacters->contains($altChar));
    }

    #[Test]
    public function main_characters_returns_empty_collection_when_no_main_characters_exist(): void
    {
        $guildRank = $this->create(['sort_order' => 0, 'name' => 'Officer']);
        Character::factory()->create(['rank_id' => $guildRank->id, 'is_main' => false]);

        $this->assertCount(0, $guildRank->mainCharacters);
    }
}
