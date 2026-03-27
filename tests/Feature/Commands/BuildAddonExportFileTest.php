<?php

namespace Tests\Feature\Commands;

use App\Jobs\BuildAddonExportFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class BuildAddonExportFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_the_export_job(): void
    {
        Bus::fake();

        $this->artisan('app:prep-addon-data')->assertExitCode(0);

        Bus::assertDispatched(BuildAddonExportFile::class);
    }
}
