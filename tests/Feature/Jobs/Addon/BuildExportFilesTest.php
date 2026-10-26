<?php

namespace Tests\Feature\Jobs\Addon;

use App\Jobs\Addon\BuildExportFile;
use App\Jobs\Addon\BuildExportFiles;
use App\Models\GameVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ReleasesAddonExportLocks;
use Tests\TestCase;

#[Group('platform')]
class BuildExportFilesTest extends TestCase
{
    use RefreshDatabase;
    use ReleasesAddonExportLocks;

    #[Test]
    public function it_has_the_addon_tag(): void
    {
        $this->assertSame(['regrowth-addon'], (new BuildExportFiles)->tags());
    }

    #[Test]
    public function it_dispatches_one_build_per_current_roster(): void
    {
        $versions = GameVersion::factory()->fetchableRoster()->count(2)->create();
        GameVersion::factory()->create(['blizzard_namespace' => null]);
        $this->releaseAddonExportLocks();
        Queue::fake();

        (new BuildExportFiles)->handle();

        Queue::assertPushed(BuildExportFile::class, 2);
        foreach ($versions as $version) {
            Queue::assertPushed(BuildExportFile::class, fn ($job) => $job->gameVersionId === $version->id);
        }
    }
}
