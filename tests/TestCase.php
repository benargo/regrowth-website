<?php

namespace Tests;

use App\Listeners\DispatchCharacterUpdates;
use App\Listeners\FetchGuildRoster;
use App\Listeners\FlushLootCouncilCache;
use App\Listeners\HandleGrmUpload;
use App\Listeners\ScheduleAddonExportBuild;
use App\Services\Blizzard\ItemService;
use App\Services\Blizzard\MediaService;
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
        $this->mock(FlushLootCouncilCache::class)->shouldReceive('handle');
        $this->mock(HandleGrmUpload::class)->shouldReceive('handle');

        $this->mock(ItemService::class, function ($mock) {
            $mock->shouldReceive('find')
                ->andReturnUsing(fn (int $id) => ['id' => $id, 'name' => "Test Item {$id}"]);
            $mock->shouldReceive('media')
                ->andReturn(['assets' => [['key' => 'icon', 'value' => 'https://example.com/icon.jpg']]]);
        });

        $this->mock(MediaService::class, function ($mock) {
            $mock->shouldReceive('find')
                ->andReturn(['assets' => [['key' => 'icon', 'value' => 'https://example.com/icon.jpg', 'file_data_id' => 1]]]);
            $mock->shouldReceive('getAssetUrls')
                ->andReturn([1 => 'https://example.com/icon.jpg']);
            $mock->shouldReceive('getIconUrl')
                ->andReturn('https://example.com/icon.jpg');
            $mock->shouldReceive('getIconUrlByName')
                ->andReturn('https://example.com/icon.jpg');
        });
    }
}
