<?php

namespace Tests\Feature\Actions\Datasets;

use App\Actions\Datasets\DeleteDatasetRecord;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class DeleteDatasetRecordTest extends TestCase
{
    use RefreshDatabase;

    #[Group('happy-path')]
    #[Test]
    public function it_deletes_an_unused_record(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $refusal = DeleteDatasetRecord::run(User::factory()->officer()->create(), $gameVersion, 'Era');

        $this->assertNull($refusal);
        $this->assertModelMissing($gameVersion);
    }

    #[Test]
    public function it_refuses_while_another_officer_holds_the_edit_lock(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $gameVersion->acquireEditLock(User::factory()->officer()->create());

        $refusal = DeleteDatasetRecord::run(User::factory()->officer()->create(), $gameVersion, 'Era');

        $this->assertSame("Someone is editing Era, so it can't be deleted right now.", $refusal);
        $this->assertModelExists($gameVersion);
    }

    #[Test]
    public function it_refuses_while_the_record_is_in_use(): void
    {
        $gameVersion = GameVersion::factory()->create();
        Phase::factory()->for($gameVersion)->create();

        $refusal = DeleteDatasetRecord::run(User::factory()->officer()->create(), $gameVersion, 'Era');

        $this->assertSame('Era is still linked to other records and cannot be deleted.', $refusal);
        $this->assertModelExists($gameVersion);
    }
}
