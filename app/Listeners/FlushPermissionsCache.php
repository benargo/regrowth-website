<?php

namespace App\Listeners;

use App\Events\DiscordRoleUpdated;
use App\Events\PermissionUpdated;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

class FlushPermissionsCache
{
    /**
     * Create the event listener.
     */
    public function __construct(
        protected PermissionRegistrar $permissionRegistrar
    ) {}

    /**
     * Handle the event.
     */
    public function handle(DiscordRoleUpdated|PermissionUpdated $event): void
    {
        Cache::tags('permissions')->flush();
        $this->permissionRegistrar->forgetCachedPermissions();
    }
}
