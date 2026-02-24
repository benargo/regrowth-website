<?php

namespace Tests;

use App\Listeners\PrepareRegrowthAddonData;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->mock(PrepareRegrowthAddonData::class)->shouldReceive('handle');
    }
}
