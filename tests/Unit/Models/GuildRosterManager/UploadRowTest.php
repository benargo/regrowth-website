<?php

namespace Tests\Unit\Models\GuildRosterManager;

use App\Enums\GuildRosterManager\RowReason;
use App\Enums\GuildRosterManager\RowStatus;
use App\Models\Character;
use App\Models\GuildRosterManager\Upload;
use App\Models\GuildRosterManager\UploadRow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

#[Group('characters')]
class UploadRowTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return UploadRow::class;
    }

    #[Test]
    public function it_uses_the_grm_upload_rows_table(): void
    {
        $this->assertSame('grm_upload_rows', $this->make()->getTable());
    }

    #[Test]
    public function it_has_the_expected_fillable_attributes(): void
    {
        $this->assertFillableAttribute($this->make(), ['upload_id', 'character_id', 'guid', 'name', 'status', 'reason', 'message', 'is_latest']);
    }

    #[Test]
    public function it_casts_status_and_reason_to_enums(): void
    {
        $row = $this->create(['status' => 'warning', 'reason' => 'name_clash'])->fresh();

        $this->assertSame(RowStatus::Warning, $row->status);
        $this->assertSame(RowReason::NameClash, $row->reason);
    }

    #[Test]
    public function it_casts_is_latest_to_a_boolean(): void
    {
        $this->assertFalse($this->create(['is_latest' => 0])->fresh()->is_latest);
    }

    #[Test]
    public function it_belongs_to_an_upload(): void
    {
        $upload = Upload::factory()->create();
        $row = $this->create(['upload_id' => $upload->id]);

        $this->assertTrue($row->upload->is($upload));
    }

    #[Test]
    public function it_belongs_to_the_character_it_was_applied_to(): void
    {
        $character = Character::factory()->create();
        $row = $this->create(['character_id' => $character->id]);

        $this->assertTrue($row->character->is($character));
    }

    #[Test]
    public function it_has_no_character_when_the_row_was_not_applied(): void
    {
        $row = $this->create(['character_id' => null]);

        $this->assertNull($row->character);
    }

    #[Test]
    public function deleting_the_character_keeps_the_row_without_a_character(): void
    {
        $character = Character::factory()->create();
        $row = $this->create(['character_id' => $character->id]);

        $character->delete();

        $this->assertModelExists($row);
        $this->assertNull($row->fresh()->character_id);
    }
}
