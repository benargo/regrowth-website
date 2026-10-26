<?php

namespace Tests\Feature\Console\Commands;

use App\Jobs\Addon\BuildExportFile;
use App\Jobs\Addon\BuildExportFiles;
use App\Models\GameVersion;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ReleasesAddonExportLocks;
use Tests\TestCase;

#[Group('platform')]
class BuildAddonExportFileTest extends TestCase
{
    use RefreshDatabase;
    use ReleasesAddonExportLocks;

    #[Test]
    public function it_dispatches_builds_for_every_current_roster(): void
    {
        Queue::fake();

        $this->artisan('app:prep-addon-data')->assertExitCode(0);

        Queue::assertPushed(BuildExportFiles::class);
    }

    #[Test]
    public function it_builds_only_the_named_game_version(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create(['slug' => 'named-version']);
        $this->releaseAddonExportLocks();
        Queue::fake();

        $this->artisan('app:prep-addon-data', ['--game-version' => 'named-version'])->assertExitCode(0);

        Queue::assertPushed(BuildExportFile::class, fn ($job) => $job->gameVersionId === $gameVersion->id);
        Queue::assertNotPushed(BuildExportFiles::class);
    }

    #[Test]
    public function it_fails_for_an_unknown_game_version(): void
    {
        Queue::fake();

        $this->artisan('app:prep-addon-data', ['--game-version' => 'unknown-slug'])
            ->expectsOutputToContain('unknown-slug')
            ->assertFailed();

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_schedules_the_fan_out_job_daily(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => $event->description === 'build-addon-export-files');

        $this->assertNotNull($event);
        $this->assertSame('15 3 * * *', $event->expression);
    }
}
