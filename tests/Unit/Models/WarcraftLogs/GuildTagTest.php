<?php

namespace Tests\Unit\Models\WarcraftLogs;

use App\Contracts\Models\DatasetModel;
use App\Models\Report;
use App\Models\WarcraftLogs\Guild;
use App\Models\WarcraftLogs\GuildTag;
use App\Observers\WarcraftLogs\GuildTagObserver;
use App\Policies\WarcraftLogsGuildPolicy;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

#[Group('characters')]
class GuildTagTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return GuildTag::class;
    }

    #[Test]
    public function it_is_observed_by_guild_tag_observer(): void
    {
        $attributes = (new \ReflectionClass(GuildTag::class))
            ->getAttributes(ObservedBy::class);

        $this->assertNotEmpty($attributes);

        $observerClasses = $attributes[0]->getArguments()[0];
        $this->assertContains(GuildTagObserver::class, $observerClasses);
    }

    #[Test]
    public function it_uses_warcraft_logs_guild_tags_table(): void
    {
        $model = new GuildTag;

        $this->assertSame('warcraft_logs_guild_tags', $model->getTable());
    }

    #[Test]
    public function it_is_governed_by_the_warcraft_logs_guild_policy_not_the_dataset_policy(): void
    {
        $this->assertInstanceOf(WarcraftLogsGuildPolicy::class, Gate::getPolicyFor(GuildTag::class));
        $this->assertNotInstanceOf(DatasetModel::class, new GuildTag);
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
            'warcraft_logs_guild_id',
        ]);
    }

    #[Test]
    public function it_declares_fillable_via_attribute(): void
    {
        $model = new GuildTag;

        $this->assertFillableAttribute($model, [
            'id',
            'name',
            'count_attendance',
            'warcraft_logs_guild_id',
        ]);
    }

    #[Test]
    public function it_has_expected_casts(): void
    {
        $model = new GuildTag;

        $this->assertCasts($model, [
            'count_attendance' => 'boolean',
        ]);
    }

    #[Test]
    public function it_has_default_count_attendance_of_false(): void
    {
        $model = new GuildTag;

        $this->assertFalse($model->count_attendance);
    }

    // ==================== persistence ====================

    #[Test]
    public function its_table_has_no_phase_column(): void
    {
        $this->assertFalse(Schema::hasColumn('warcraft_logs_guild_tags', 'phase_id'));
        $this->assertFalse(Schema::hasColumn('warcraft_logs_guild_tags', 'tbc_phase_id'));
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
        $guild = Guild::factory()->create();

        $guildTag = $this->create([
            'name' => 'Main Roster',
            'count_attendance' => true,
            'warcraft_logs_guild_id' => $guild->id,
        ]);

        $this->assertTableHas([
            'name' => 'Main Roster',
            'count_attendance' => true,
            'warcraft_logs_guild_id' => $guild->id,
        ]);
        $this->assertModelExists($guildTag);
    }

    // ==================== factory states ====================

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

    // ==================== guild relationship ====================

    #[Test]
    public function it_belongs_to_a_guild(): void
    {
        $guild = Guild::factory()->create();
        $guildTag = $this->factory()->forGuild($guild)->create();

        $this->assertRelation($guildTag, 'guild', BelongsTo::class);
        $this->assertTrue($guildTag->guild->is($guild));
    }

    #[Test]
    public function guild_is_null_when_the_tag_has_no_guild(): void
    {
        $this->assertNull($this->create()->guild);
    }

    #[Test]
    public function it_has_no_game_version_relationship_of_its_own(): void
    {
        $this->assertFalse(method_exists(GuildTag::class, 'gameVersion'));
    }

    // ==================== reports relationship ====================

    #[Test]
    public function it_has_many_reports(): void
    {
        $guildTag = $this->create();

        $this->assertInstanceOf(HasMany::class, $guildTag->reports());
    }

    #[Test]
    public function it_can_have_reports(): void
    {
        $guildTag = $this->create();
        Report::factory()->count(3)->withGuildTag($guildTag)->create();

        $this->assertCount(3, $guildTag->reports);
    }

    #[Test]
    public function reports_returns_empty_collection_when_none_associated(): void
    {
        $guildTag = $this->create();

        $this->assertCount(0, $guildTag->reports);
    }
}
