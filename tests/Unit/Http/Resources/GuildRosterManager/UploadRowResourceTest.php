<?php

namespace Tests\Unit\Http\Resources\GuildRosterManager;

use App\Enums\GuildRosterManager\RowReason;
use App\Enums\GuildRosterManager\RowStatus;
use App\Http\Resources\GuildRosterManager\UploadRowResource;
use App\Models\Character;
use App\Models\GuildRosterManager\UploadRow;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[Group('characters')]
class UploadRowResourceTest extends TestCase
{
    #[Test]
    #[Group('contract')]
    public function it_returns_the_row_shape_without_the_character(): void
    {
        $row = $this->makeRow();

        $this->assertSame([
            'characterId' => 12,
            'id' => 34,
            'message' => 'Rank changed',
            'name' => 'Thrall',
            'reason' => RowReason::Updated,
            'status' => RowStatus::Processed,
            'meta' => [
                'is_expected' => false,
            ],
        ], (new UploadRowResource($row))->resolve(new Request));
    }

    #[Test]
    public function it_returns_the_character_slug_when_the_character_is_loaded(): void
    {
        $row = $this->makeRow();
        $row->setRelation('character', (new Character)->forceFill(['name' => 'Thrall', 'surname' => 'Durotan']));

        $this->assertSame('thrall-durotan', (new UploadRowResource($row))->resolve(new Request)['characterSlug']);
    }

    #[Test]
    public function it_returns_a_null_character_slug_when_the_loaded_character_is_missing(): void
    {
        $row = $this->makeRow(['character_id' => null]);
        $row->setRelation('character', null);

        $array = (new UploadRowResource($row))->resolve(new Request);

        $this->assertArrayHasKey('characterSlug', $array);
        $this->assertNull($array['characterSlug']);
        $this->assertNull($array['characterId']);
    }

    #[Test]
    #[TestWith([RowReason::BelowMinimumLevel, true])]
    #[TestWith([RowReason::NotTracked, true])]
    #[TestWith([RowReason::NotConfirmedInGuild, true])]
    #[TestWith([RowReason::PlaceholderName, true])]
    #[TestWith([RowReason::UnknownRank, false])]
    #[TestWith([RowReason::Error, false])]
    public function it_flags_whether_the_reason_is_expected(RowReason $reason, bool $isExpected): void
    {
        $row = $this->makeRow(['status' => RowStatus::Skipped, 'reason' => $reason]);

        $this->assertSame($isExpected, (new UploadRowResource($row))->resolve(new Request)['meta']['is_expected']);
    }

    // ==================== helpers ====================

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeRow(array $attributes = []): UploadRow
    {
        return (new UploadRow)->forceFill([
            'id' => 34,
            'character_id' => 12,
            'name' => 'Thrall',
            'status' => RowStatus::Processed,
            'reason' => RowReason::Updated,
            'message' => 'Rank changed',
            ...$attributes,
        ]);
    }
}
