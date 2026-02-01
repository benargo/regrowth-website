<?php

namespace Tests\Unit\Models\WarcraftLogs;

use App\Models\TBC\Phase;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class GuildTagTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return GuildTag::class;
    }

    #[Test]
    public function it_uses_wcl_guild_tags_table(): void
    {
        $model = new GuildTag;

        $this->assertSame('wcl_guild_tags', $model->getTable());
    }

    #[Test]
    public function it_uses_auto_incrementing_id(): void
    {
        $model = new GuildTag;

        $this->assertSame('id', $model->getKeyName());
        $this->assertTrue($model->getIncrementing());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new GuildTag;

        $this->assertFillable($model, [
            'id',
            'name',
            'count_attendance',
            'tbc_phase_id',
        ]);
    }

    #[Test]
    public function it_has_default_count_attendance_of_false(): void
    {
        $model = new GuildTag;

        $this->assertFalse($model->count_attendance);
    }

    #[Test]
    public function it_can_be_created_with_required_attributes(): void
    {
        $guildTag = $this->create([
            'name' => 'Raid Team A',
        ]);

        $this->assertTableHas(['name' => 'Raid Team A']);
        $this->assertModelExists($guildTag);
    }

    #[Test]
    public function it_can_be_created_with_all_attributes(): void
    {
        $phase = Phase::factory()->create();

        $guildTag = $this->create([
            'name' => 'Main Roster',
            'count_attendance' => true,
            'tbc_phase_id' => $phase->id,
        ]);

        $this->assertTableHas([
            'name' => 'Main Roster',
            'count_attendance' => true,
            'tbc_phase_id' => $phase->id,
        ]);
        $this->assertModelExists($guildTag);
    }

    #[Test]
    public function it_allows_null_tbc_phase_id(): void
    {
        $guildTag = $this->create([
            'name' => 'Unassigned Tag',
            'tbc_phase_id' => null,
        ]);

        $this->assertNull($guildTag->tbc_phase_id);
        $this->assertModelExists($guildTag);
    }

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $guildTag = $this->create();

        $this->assertNotEmpty($guildTag->name);
        $this->assertModelExists($guildTag);
    }

    #[Test]
    public function factory_counts_attendance_state_sets_count_attendance_to_true(): void
    {
        $guildTag = $this->factory()->countsAttendance()->create();

        $this->assertTrue($guildTag->count_attendance);
    }

    #[Test]
    public function factory_does_not_count_attendance_state_sets_count_attendance_to_false(): void
    {
        $guildTag = $this->factory()->doesNotCountAttendance()->create();

        $this->assertFalse($guildTag->count_attendance);
    }

    #[Test]
    public function factory_with_phase_state_associates_a_phase(): void
    {
        $guildTag = $this->factory()->withPhase()->create();

        $this->assertNotNull($guildTag->tbc_phase_id);
        $this->assertNotNull($guildTag->phase);
    }

    #[Test]
    public function factory_with_phase_state_accepts_specific_phase(): void
    {
        $phase = Phase::factory()->create(['description' => 'Test Phase']);

        $guildTag = $this->factory()->withPhase($phase)->create();

        $this->assertSame($phase->id, $guildTag->tbc_phase_id);
        $this->assertSame('Test Phase', $guildTag->phase->description);
    }

    #[Test]
    public function factory_without_phase_state_sets_null_phase(): void
    {
        $guildTag = $this->factory()->withoutPhase()->create();

        $this->assertNull($guildTag->tbc_phase_id);
    }

    #[Test]
    public function it_belongs_to_a_phase(): void
    {
        $phase = Phase::factory()->create();
        $guildTag = $this->create(['tbc_phase_id' => $phase->id]);

        $this->assertRelation($guildTag, 'phase', BelongsTo::class);
        $this->assertSame($phase->id, $guildTag->phase->id);
    }

    #[Test]
    public function phase_relationship_returns_null_when_no_phase_associated(): void
    {
        $guildTag = $this->create(['tbc_phase_id' => null]);

        $this->assertNull($guildTag->phase);
    }
}
