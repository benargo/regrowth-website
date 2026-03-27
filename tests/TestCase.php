<?php

namespace Tests;

use App\Listeners\DispatchCharacterUpdates;
use App\Listeners\FetchGuildRoster;
use App\Listeners\HandleGrmUpload;
use App\Listeners\ScheduleAddonExportBuild;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->mock(ScheduleAddonExportBuild::class)->shouldReceive('handle');
        $this->mock(FetchGuildRoster::class)->shouldReceive('handle');
        $this->mock(DispatchCharacterUpdates::class)->shouldReceive('handle');
        $this->mock(HandleGrmUpload::class)->shouldReceive('handle');
    }
}
