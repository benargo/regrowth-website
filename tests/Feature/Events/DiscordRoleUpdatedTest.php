<?php

namespace Tests\Feature\Events;

use App\Listeners\FlushPermissionsCache;
use App\Models\DiscordRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('auth')]
class DiscordRoleUpdatedTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function updating_a_role_flushes_the_permissions_cache(): void
    {
        $this->app->forgetInstance(FlushPermissionsCache::class);
        $role = DiscordRole::factory()->create();
        Cache::tags(['permissions'])->put('probe', true);

        $role->update(['is_visible' => true]);

        $this->assertNull(Cache::tags(['permissions'])->get('probe'));
    }
}
