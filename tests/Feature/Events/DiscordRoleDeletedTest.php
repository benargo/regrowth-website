<?php

namespace Tests\Feature\Events;

use App\Listeners\FlushPermissionsCache;
use App\Models\DiscordRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

#[Group('auth')]
class DiscordRoleDeletedTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function deleting_a_role_flushes_the_permissions_cache(): void
    {
        $this->app->forgetInstance(FlushPermissionsCache::class);
        $role = DiscordRole::factory()->create();
        Cache::tags(['permissions'])->put('probe', true);

        $role->delete();

        $this->assertNull(Cache::tags(['permissions'])->get('probe'));
    }

    #[Test]
    public function deleting_a_role_forgets_the_registrar_cached_permissions(): void
    {
        $this->app->forgetInstance(FlushPermissionsCache::class);
        $role = DiscordRole::factory()->create();
        $this->mock(PermissionRegistrar::class, function (MockInterface $registrar): void {
            $registrar->shouldReceive('forgetCachedPermissions')->once();
        });

        $role->delete();
    }
}
