<?php

namespace Tests;

use App\Jobs\Addon\BuildExportFile;
use App\Jobs\Discord\AttachAvatarToUser;
use App\Listeners\FetchGuildRoster;
use App\Listeners\FlushPermissionsCache;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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
        $this->mock(FlushPermissionsCache::class)->shouldReceive('handle');

        Queue::fake([BuildExportFile::class, AttachAvatarToUser::class]);
        Storage::fake('addon');
        Storage::fake('handbook');

        if (! self::$strayRequestsPrevented) {
            Config::preventStrayRequests();
            self::$strayRequestsPrevented = true;
        }
    }
}
