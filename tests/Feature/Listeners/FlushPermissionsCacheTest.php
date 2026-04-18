<?php

namespace Tests\Feature\Listeners;

use App\Events\PermissionUpdated;
use App\Listeners\FlushPermissionsCache;
use App\Models\Permission;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FlushPermissionsCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::tags(['permissions'])->flush();
    }

    #[Test]
    public function it_implements_should_be_unique(): void
    {
        $listener = new FlushPermissionsCache(app(PermissionRegistrar::class));

        $this->assertInstanceOf(ShouldBeUnique::class, $listener);
    }

    #[Test]
    public function permission_updated_event_flushes_permissions_cache(): void
    {
        Cache::tags(['permissions'])->put('test_key', 'test_value', now()->addMinutes(5));

        $this->assertTrue(Cache::tags(['permissions'])->has('test_key'));

        $listener = new FlushPermissionsCache(app(PermissionRegistrar::class));
        $listener->handle(new PermissionUpdated(Permission::factory()->create()));

        $this->assertFalse(Cache::tags(['permissions'])->has('test_key'));
    }

    #[Test]
    public function flushes_multiple_cache_entries(): void
    {
        Cache::tags(['permissions'])->put('key_one', 'value_one', now()->addMinutes(5));
        Cache::tags(['permissions'])->put('key_two', 'value_two', now()->addMinutes(5));

        $listener = new FlushPermissionsCache(app(PermissionRegistrar::class));
        $listener->handle(new PermissionUpdated(Permission::factory()->create()));

        $this->assertFalse(Cache::tags(['permissions'])->has('key_one'));
        $this->assertFalse(Cache::tags(['permissions'])->has('key_two'));
    }

    #[Test]
    public function does_not_flush_unrelated_cache_tags(): void
    {
        Cache::tags(['other_tag'])->put('unrelated_key', 'unrelated_value', now()->addMinutes(5));

        $listener = new FlushPermissionsCache(app(PermissionRegistrar::class));
        $listener->handle(new PermissionUpdated(Permission::factory()->create()));

        $this->assertTrue(Cache::tags(['other_tag'])->has('unrelated_key'));
    }

    #[Test]
    public function calls_forget_cached_permissions_on_registrar(): void
    {
        $registrar = $this->createMock(PermissionRegistrar::class);
        $registrar->expects($this->once())->method('forgetCachedPermissions');

        $listener = new FlushPermissionsCache($registrar);
        $listener->handle(new PermissionUpdated(Permission::factory()->create()));
    }
}
