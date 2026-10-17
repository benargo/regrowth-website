<?php

namespace Tests\Feature\Actions\Datasets;

use App\Actions\Datasets\DeleteDatasetRecord;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class DeleteDatasetRecordTest extends TestCase
{
    use RefreshDatabase;

    #[Group('happy-path')]
    #[Test]
    public function it_deletes_an_unused_record_and_redirects_to_the_index(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $response = DeleteDatasetRecord::run($this->requestBy(User::factory()->officer()->create()), $gameVersion, 'Era', 'management.game-versions.index');

        $this->assertSame(route('management.game-versions.index'), $response->getTargetUrl());
        $this->assertSame('Deleted Era.', session('success'));
        $this->assertModelMissing($gameVersion);
    }

    #[Test]
    public function it_refuses_while_another_officer_holds_the_edit_lock(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $gameVersion->acquireEditLock(User::factory()->officer()->create());

        DeleteDatasetRecord::run($this->requestBy(User::factory()->officer()->create()), $gameVersion, 'Era', 'management.game-versions.index');

        $this->assertSame("Someone is editing Era, so it can't be deleted right now.", session('error'));
        $this->assertModelExists($gameVersion);
    }

    #[Test]
    public function it_refuses_while_the_record_is_in_use(): void
    {
        $gameVersion = GameVersion::factory()->create();
        Phase::factory()->for($gameVersion)->create();

        DeleteDatasetRecord::run($this->requestBy(User::factory()->officer()->create()), $gameVersion, 'Era', 'management.game-versions.index');

        $this->assertSame('Era is still linked to other records and cannot be deleted.', session('error'));
        $this->assertModelExists($gameVersion);
    }

    // ==================== helpers ====================

    private function requestBy(User $user): Request
    {
        $request = Request::create('/manage/game-versions', 'DELETE');
        $request->setUserResolver(fn (): User => $user);

        return $request;
    }
}
