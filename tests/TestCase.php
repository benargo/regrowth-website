<?php

namespace Tests;

use App\Listeners\FetchGuildRoster;
use App\Listeners\FlushLootCouncilCache;
use App\Listeners\FlushPermissionsCache;
use App\Listeners\ScheduleAddonExportBuild;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Saloon\Config;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    private static bool $strayRequestsPrevented = false;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->mock(FetchGuildRoster::class)->shouldReceive('handle');
        $this->mock(FlushLootCouncilCache::class)->shouldReceive('handle');
        $this->mock(FlushPermissionsCache::class)->shouldReceive('handle');
        $this->mock(ScheduleAddonExportBuild::class)->shouldReceive('handle');

        if (! self::$strayRequestsPrevented) {
            Config::preventStrayRequests();
            self::$strayRequestsPrevented = true;
        }
    }
}
