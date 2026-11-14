<?php

namespace Tests\Feature\Models\GuildRosterManager;

use App\Models\GuildRosterManager\Ban;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class BanTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_hides_the_reason_from_serialisation(): void
    {
        $ban = Ban::factory()->create(['reason' => 'Stole from the guild bank']);

        $this->assertArrayNotHasKey('reason', $ban->toArray());
    }

    #[Test]
    public function it_uses_a_uuid_primary_key(): void
    {
        $ban = Ban::factory()->create();

        $this->assertTrue(Str::isUuid($ban->id));
    }

    #[Test]
    public function it_refuses_a_second_ban_row_for_the_same_guid_and_game_version(): void
    {
        $ban = Ban::factory()->create();

        $this->expectException(QueryException::class);

        Ban::factory()->create([
            'game_version_id' => $ban->game_version_id,
            'grm_guid' => $ban->grm_guid,
        ]);
    }

    #[Test]
    public function active_excludes_unbanned_rows(): void
    {
        $active = Ban::factory()->create();
        Ban::factory()->unbanned()->create();

        $this->assertSame([$active->id], Ban::query()->active()->pluck('id')->all());
    }
}
